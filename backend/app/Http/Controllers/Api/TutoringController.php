<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tutoring;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TutoringController extends Controller
{
    public function index(): JsonResponse
    {
        $tutorings = Tutoring::with('user')->get();
        return response()->json(['data' => $tutorings], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'subject_name' => 'required|string|max:255',
            'tutor_name' => 'required|string|max:255',
            'scheduled_at' => 'required|date|after:now',
            'status' => 'required|in:scheduled,completed,cancelled',
            'meeting_link_or_place' => 'nullable|string|max:255',
        ]);

        $tutoring = Tutoring::create($validated);
        return response()->json(['message' => 'Tutoría programada', 'data' => $tutoring], 201);
    }

    public function show(Tutoring $tutoring): JsonResponse
    {
        return response()->json(['data' => $tutoring->load('user')], 200);
    }

    public function update(Request $request, Tutoring $tutoring): JsonResponse
    {
        $validated = $request->validate([
            'subject_name' => 'string|max:255',
            'tutor_name' => 'string|max:255',
            'scheduled_at' => 'date',
            'status' => 'in:scheduled,completed,cancelled',
            'meeting_link_or_place' => 'nullable|string|max:255',
        ]);

        $tutoring->update($validated);
        return response()->json(['message' => 'Tutoría actualizada', 'data' => $tutoring], 200);
    }

    public function destroy(Tutoring $tutoring): JsonResponse
    {
        $tutoring->delete();
        return response()->json(['message' => 'Tutoría cancelada y removida del sistema'], 200);
    }
}