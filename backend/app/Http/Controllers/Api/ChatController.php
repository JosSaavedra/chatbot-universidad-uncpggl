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

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $request->user()->id,
            'role'            => 'user',
            'content'         => $request->input('message'),
        ]);

        // El usuario autenticado solo accede a SU propia información.
        $result = $this->chatbot->processQuery(
            message: $request->input('message'),
            userId: $request->user()->id,
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
