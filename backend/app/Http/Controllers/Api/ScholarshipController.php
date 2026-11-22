<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scholarship;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ScholarshipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Listado de becas: la identidad se toma EXCLUSIVAMENTE del token de
        // Sanctum. Un estudiante sólo ve sus propias becas; el administrador
        // ve todas y puede filtrar por `user_id`.
        Gate::authorize('viewAny', Scholarship::class);

        $user  = $request->user();
        $query = Scholarship::with('user');

        if (! $user->is_admin) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json(['data' => $query->get()], 200);
    }

    public function store(Request $request): JsonResponse
    {
        // Alta de beca: exclusiva de administradores (el beneficiario es
        // asignado por la administración, nunca por el estudiante).
        Gate::authorize('create', Scholarship::class);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:academic,sports,socioeconomic,culture,academica,deportiva,socioeconómica,cultural',
            'coverage_percentage' => 'required|numeric|between:0,100',
            'status' => 'required|in:active,suspended,cancelled,active,suspended,cancelled',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string',
        ]);

        $scholarship = Scholarship::create($validated);
        return response()->json(['message' => 'Beca asignada con éxito', 'data' => $scholarship], 201);
    }

    public function show(Scholarship $scholarship): JsonResponse
    {
        // Ficha de beca: sólo su dueño o un administrador (ScholarshipPolicy).
        Gate::authorize('view', $scholarship);

        return response()->json(['data' => $scholarship->load('user')], 200);
    }

    public function update(Request $request, Scholarship $scholarship): JsonResponse
    {
        // Modificar una beca: exclusivo de administradores. El `user_id` no
        // forma parte de los campos validados, así que la identidad del
        // beneficiario nunca puede cambiar desde el cliente.
        Gate::authorize('update', $scholarship);

        $validated = $request->validate([
            'type' => 'in:academic,sports,socioeconomic,culture,academica,deportiva,socioeconómica,cultural',
            'coverage_percentage' => 'numeric|between:0,100',
            'status' => 'in:active,suspended,cancelled',
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'description' => 'nullable|string',
        ]);

        $scholarship->update($validated);
        return response()->json(['message' => 'Beca modificada correctamente', 'data' => $scholarship], 200);
    }

    public function destroy(Scholarship $scholarship): JsonResponse
    {
        // Borrado de beca: exclusivo de administradores.
        Gate::authorize('delete', $scholarship);

        $scholarship->delete();
        return response()->json(['message' => 'Registro de beca eliminado'], 200);
    }
}
