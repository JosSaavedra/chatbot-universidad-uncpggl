<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tutoring;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TutoringController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Listado de tutorías: la identidad se toma EXCLUSIVAMENTE del token
        // de Sanctum. Un estudiante sólo ve sus propias tutorías; el
        // administrador ve todas y puede filtrar por `user_id`.
        Gate::authorize('viewAny', Tutoring::class);

        $user  = $request->user();
        $query = Tutoring::with('user');

        if (! $user->is_admin) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json(['data' => $query->get()], 200);
    }

    public function store(Request $request): JsonResponse
    {
        // Alta de tutoría: exclusiva de administradores (el estudiante es
        // asignado por la administración, nunca por el cliente).
        Gate::authorize('create', Tutoring::class);

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
        // Ficha de tutoría: sólo su dueño o un administrador (TutoringPolicy).
        Gate::authorize('view', $tutoring);

        return response()->json(['data' => $tutoring->load('user')], 200);
    }

    public function update(Request $request, Tutoring $tutoring): JsonResponse
    {
        // Modificar una tutoría: exclusivo de administradores. El `user_id` no
        // forma parte de los campos validados, así que la identidad nunca puede
        // cambiar desde el cliente.
        Gate::authorize('update', $tutoring);

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
        // Borrado de tutoría: exclusivo de administradores.
        Gate::authorize('delete', $tutoring);

        $tutoring->delete();
        return response()->json(['message' => 'Tutoría cancelada y removida del sistema'], 200);
    }
}
