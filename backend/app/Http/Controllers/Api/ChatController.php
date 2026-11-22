<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatbotService;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class ChatController extends Controller
{
    public function __construct(protected ChatbotService $chatbot) {}

    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'message'    => 'required|string|max:500',
            'session_id' => 'nullable|string|max:255',
        ]);

        $conversation = $this->conversationFor(
            $request,
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
        $suggestions = $result['intent'] === 'carnet_not_found'
            ? null : $this->chatbot->chatSuggestions($request->input('message'), null, $history);

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => null,
            'role'            => 'bot',
            'content'         => $result['text'],
            'data'            => $suggestions,
        ]);

        $payload = [
            'reply'      => $result['text'],
            'intent'     => $result['intent'],
            'data'       => $suggestions,
            'session_id' => $conversation->session_id,
        ];
        if ($conversation->wasRecentlyCreated) {
            $payload['guest_token'] = Crypt::encryptString(json_encode([
                'session' => $conversation->session_id,
                'expires' => now()->addDay()->timestamp,
            ], JSON_THROW_ON_ERROR));
        }

        return response()->json($payload);
    }

    public function getHistory(Request $request): JsonResponse
    {
        $request->validate(['session_id' => 'required|string|max:255']);
        $userId = $this->historyOwner($request);
        $conversation = $this->existingConversation($request, $userId);

        $messages = Message::where('conversation_id', $conversation->id)
            ->when($userId === null, fn ($query) => $query->whereNull('user_id'))
            ->orderBy('created_at')
            ->get(['user_id', 'role', 'content', 'data', 'created_at'])
            ->makeHidden('user_id');

        return response()->json(['messages' => $messages]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['session_id' => 'required|string|max:255']);
        $this->existingConversation($request, $this->historyOwner($request))->delete();

        return response()->json(null, 204);
    }

    private function historyOwner(Request $request): ?int
    {
        $userId = auth('sanctum')->id();
        abort_if($request->bearerToken() !== null && $userId === null, 401);

        return $userId;
    }

    public function sendAuth(Request $request): JsonResponse
    {
        $request->validate([
            'message'    => 'required|string|max:500',
            'session_id' => 'nullable|string|max:255',
        ]);

        $userId    = (int) $request->user()->id;
        $conversation = $this->conversationFor(
            $request,
            $userId,
            (string) $request->input('message'),
        );

        $history = $this->historyFor($conversation, $userId);

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
        $suggestions = $result['intent'] === 'carnet_not_found'
            ? null : $this->chatbot->chatSuggestions($request->input('message'), $student, $history);

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
    private function historyFor(Conversation $conversation, ?int $userId = null): array
    {
        $exchanges = max(1, (int) config('chatbot.memory.exchanges', 6));

        return Message::where('conversation_id', $conversation->id)
            ->when($userId === null, fn ($query) => $query->whereNull('user_id'))
            ->orderByDesc('id')
            ->take($exchanges * 2)
            ->get(['user_id', 'role', 'content', 'data'])
            ->reverse()
            ->values()
            ->map(fn (Message $m) => [
                'role'    => $m->role,
                'content' => $m->content,
                'data'    => $m->data,
            ])
            ->all();
    }

    private function conversationFor(Request $request, ?int $userId, string $message): Conversation
    {
        if ($request->filled('session_id')) {
            return $this->existingConversation($request, $userId);
        }

        return Conversation::create([
            'session_id' => bin2hex(random_bytes(32)),
            'user_id' => $userId,
            'title' => mb_substr($message, 0, 50),
        ]);
    }

    private function existingConversation(Request $request, ?int $userId): Conversation
    {
        $sessionId = (string) $request->input('session_id');

        if ($userId === null) {
            $proof = (string) $request->header('X-Chat-Guest-Token');
            abort_if(strlen($proof) > 1024 || $proof === '', 403);
            try {
                $subject = json_decode(Crypt::decryptString($proof), true);
            } catch (DecryptException) {
                abort(403);
            }
            abort_unless(is_array($subject)
                && is_int($subject['expires'] ?? null) && $subject['expires'] > now()->timestamp
                && is_string($subject['session'] ?? null) && hash_equals($sessionId, $subject['session']), 403);
        }

        $conversation = Conversation::where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->first();

        abort_if($conversation === null, 403);

        return $conversation;
    }
}
