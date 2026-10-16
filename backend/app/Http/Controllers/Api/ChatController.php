<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatbotService;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(protected ChatbotService $chatbot) {}

    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'message'    => 'required|string|max:500',
            'session_id' => 'required|string',
        ]);

        $conversation = $this->conversationFor(
            (string) $request->input('session_id'),
            null,
            (string) $request->input('message'),
        );

        // Ventana de memoria de ESTA conversación (se lee antes de guardar el
        // mensaje nuevo). Nunca mezcla sesiones ni estudiantes.
        $history = $this->historyFor($conversation);

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => null,
            'role'            => 'user',
            'content'         => $request->input('message'),
        ]);

        // Endpoint público: sin usuario autenticado solo se responde información pública.
        $result = $this->chatbot->processQuery(
            message: $request->input('message'),
            userId: null,
            history: $history,
        );

        // Se identifica la intención del estudiante para decidir qué tarjeta
        // mostrar (horario, pagos, calendario, admisión, opciones de matrícula
        // o carreras). En el endpoint público no hay estudiante identificado.
        $suggestions = $this->chatbot->chatSuggestions($request->input('message'), null, $history);

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => null,
            'role'            => 'bot',
            'content'         => $result['text'],
            'data'            => $suggestions,
        ]);

        return response()->json([
            'reply'      => $result['text'],
            'intent'     => $result['intent'],
            'data'       => $suggestions,
            'session_id' => $conversation->session_id,
        ]);
    }

    public function getHistory(Request $request): JsonResponse
    {
        $request->validate(['session_id' => 'required|string']);

        $conversation = Conversation::where('session_id', $request->input('session_id'))->first();

        if (!$conversation) {
            return response()->json(['messages' => []]);
        }

        // Las conversaciones ligadas a una cuenta pueden contener datos
        // personales (horario, pagos, notas): solo las lee su dueño.
        if ($conversation->user_id !== null) {
            $user = auth('sanctum')->user();

            if (!$user || (int) $user->id !== (int) $conversation->user_id) {
                return response()->json([
                    'message' => 'No autorizado para consultar esta conversación.',
                ], 403);
            }
        }

        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get(['role', 'content', 'data', 'created_at']);

        return response()->json(['messages' => $messages]);
    }

    public function sendAuth(Request $request): JsonResponse
    {
        $request->validate([
            'message'    => 'required|string|max:500',
            'session_id' => 'nullable|string',
        ]);

        $userId    = (int) $request->user()->id;
        $sessionId = (string) ($request->input('session_id') ?: 'auth_' . $userId);

        $conversation = $this->conversationFor(
            $sessionId,
            $userId,
            (string) $request->input('message'),
        );

        $history = $this->historyFor($conversation);

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $userId,
            'role'            => 'user',
            'content'         => $request->input('message'),
        ]);

        // El usuario autenticado solo accede a SU propia información.
        $result = $this->chatbot->processQuery(
            message: $request->input('message'),
            userId: $userId,
            history: $history,
        );

        // Para el horario personalizado se resuelve el estudiante de esta cuenta.
        $student = Student::where('user_id', $userId)->first();
        $suggestions = $this->chatbot->chatSuggestions($request->input('message'), $student, $history);

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $userId,
            'role'            => 'bot',
            'content'         => $result['text'],
            'data'            => $suggestions,
        ]);

        return response()->json([
            'reply'      => $result['text'],
            'intent'     => $result['intent'],
            'data'       => $suggestions,
            'session_id' => $conversation->session_id,
        ]);
    }

    /**
     * Ventana de memoria de la conversación ACTUAL.
     *
     * Se lee antes de guardar el mensaje nuevo y se limita a
     * `chatbot.memory.exchanges` intercambios. Como recibe la conversación ya
     * resuelta y protegida, nunca mezcla sesiones ni estudiantes distintos.
     *
     * @return array<int, array{role:string, content:string, data:mixed}>
     */
    private function historyFor(Conversation $conversation): array
    {
        $exchanges = max(1, (int) config('chatbot.memory.exchanges', 6));

        return Message::where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->take($exchanges * 2)
            ->get(['role', 'content', 'data'])
            ->reverse()
            ->values()
            ->map(fn (Message $m) => [
                'role'    => $m->role,
                'content' => $m->content,
                'data'    => $m->data,
            ])
            ->all();
    }

    /**
     * Resuelve la conversación a usar en una petición.
     *
     *  - Si no existe, se crea.
     *  - En peticiones anónimas nunca se escribe en la conversación de un
     *    usuario: se usa una conversación de invitado aparte.
     *  - En peticiones autenticadas, una conversación anónima previa se reclama
     *    para el usuario (así el historial personal queda protegido), y una
     *    conversación de OTRA cuenta no se reutiliza.
     */
    private function conversationFor(string $sessionId, ?int $userId, string $message): Conversation
    {
        $title        = mb_substr($message, 0, 50);
        $conversation = Conversation::where('session_id', $sessionId)->first();

        if ($conversation === null) {
            return Conversation::create([
                'session_id' => $sessionId,
                'user_id'    => $userId,
                'title'      => $title,
            ]);
        }

        if ($userId === null) {
            if ($conversation->user_id === null) {
                return $conversation;
            }

            // Conversación de un usuario: el invitado usa una propia.
            return Conversation::firstOrCreate(
                ['session_id' => $sessionId . '_guest'],
                ['user_id' => null, 'title' => $title],
            );
        }

        if ($conversation->user_id === null) {
            // La conversación anónima previa pasa a ser del usuario que inicia sesión.
            $conversation->user_id = $userId;
            $conversation->save();

            return $conversation;
        }

        if ((int) $conversation->user_id === $userId) {
            return $conversation;
        }

        abort(403, 'La sesión de conversación pertenece a otra cuenta.');
    }
}
