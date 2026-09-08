<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatbotService;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(protected ChatbotService $chatbot) {}

    private function extractStudentCode(string $message): ?string
    {
        if (preg_match('/\b(\d{4}-[A-Z]{2,3}-\d{3})\b/', $message, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'message'    => 'required|string|max:500',
            'session_id' => 'required|string',
        ]);

        $conversation = Conversation::firstOrCreate(
            ['session_id' => $request->input('session_id')],
            ['user_id' => null, 'title' => substr($request->input('message'), 0, 50)]
        );

        $newCarnet = $this->extractStudentCode($request->input('message'));
        if ($newCarnet) {
            $conversation->update(['student_code' => $newCarnet]);
        }
        $studentCode = $newCarnet ?? $conversation->student_code;

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => null,
            'role'            => 'user',
            'content'         => $request->input('message'),
        ]);

        $result = $this->chatbot->processQuery(
            message: $request->input('message'),
            userId: null,
            studentCode: $studentCode
        );

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => null,
            'role'            => 'bot',
            'content'         => $result['text'],
        ]);

        return response()->json([
            'reply'      => $result['text'],
            'intent'     => $result['intent'],
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

        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get(['role', 'content', 'created_at']);

        return response()->json(['messages' => $messages]);
    }

    public function sendAuth(Request $request): JsonResponse
    {
        $request->validate([
            'message'    => 'required|string|max:500',
            'session_id' => 'nullable|string',
        ]);

        $sessionId = $request->input('session_id') ?? 'auth_' . $request->user()->id;

        $conversation = Conversation::firstOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => $request->user()->id, 'title' => substr($request->input('message'), 0, 50)]
        );

        $newCarnet = $this->extractStudentCode($request->input('message'));
        if ($newCarnet) {
            $conversation->update(['student_code' => $newCarnet]);
        }
        $studentCode = $newCarnet ?? $conversation->student_code;

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $request->user()->id,
            'role'            => 'user',
            'content'         => $request->input('message'),
        ]);

        $result = $this->chatbot->processQuery(
            message: $request->input('message'),
            userId: $request->user()->id,
            studentCode: $studentCode
        );

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $request->user()->id,
            'role'            => 'bot',
            'content'         => $result['text'],
        ]);

        return response()->json([
            'reply'      => $result['text'],
            'intent'     => $result['intent'],
            'session_id' => $conversation->session_id,
        ]);
    }
}
