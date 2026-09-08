<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = Conversation::where('user_id', $request->user()->id)
            ->with('messages')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json(['data' => $conversations]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $conversation = Conversation::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with('messages')
            ->firstOrFail();

        return response()->json(['data' => $conversation]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $conversation = Conversation::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $conversation->delete();

        return response()->json(['message' => 'Conversación eliminada correctamente.']);
    }
}
