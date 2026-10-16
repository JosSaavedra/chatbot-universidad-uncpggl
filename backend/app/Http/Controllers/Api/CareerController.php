<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Career;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CareerController extends Controller
{
    public function index(): JsonResponse
    {
        $careers = Career::with([
                'sedes' => function ($q) {
                    $q->where('sedes.is_active', true)->orderByDesc('sedes.is_main')->orderBy('sedes.name');
                },
                'subjects',
                'schedules' => function ($q) {
                    $q->orderBy('year_of_study')->orderBy('day_of_week')->orderBy('start_time');
                },
            ])
            ->where('is_active', true)
            ->get();
        return response()->json(['data' => $careers], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:careers,code',
            'name' => 'required|string|max:255',
            'faculty' => 'nullable|string|max:255',
            'duration_years' => 'required|integer|min:1',
            'modalidad' => 'nullable|string|max:100',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'monthly_fee' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'titulo_otorga' => 'nullable|string|max:255',
            'perfil_profesional' => 'nullable|string',
            'campo_laboral' => 'nullable|string',
            'requisitos_ingreso' => 'nullable|string',
            'pensum_url' => 'nullable|string|max:255',
            'imagen_url' => 'nullable|string|max:255',
        ]);

        $career = Career::create($validated);
        return response()->json(['message' => 'Carrera creada con éxito', 'data' => $career], 201);
    }

    public function show(Career $career): JsonResponse
    {
        $career->load([
            'sedes' => function ($q) {
                $q->where('sedes.is_active', true)->orderByDesc('sedes.is_main')->orderBy('sedes.name');
            },
            'subjects',
        ]);

        return response()->json(['data' => $career], 200);
    }

    public function update(Request $request, Career $career): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'string|unique:careers,code,' . $career->id,
            'name' => 'string|max:255',
            'faculty' => 'nullable|string|max:255',
            'duration_years' => 'integer|min:1',
            'modalidad' => 'nullable|string|max:100',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'monthly_fee' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'titulo_otorga' => 'nullable|string|max:255',
            'perfil_profesional' => 'nullable|string',
            'campo_laboral' => 'nullable|string',
            'requisitos_ingreso' => 'nullable|string',
            'pensum_url' => 'nullable|string|max:255',
            'imagen_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $career->update($validated);
        return response()->json(['message' => 'Carrera actualizada con éxito', 'data' => $career], 200);
    }

    public function destroy(Career $career): JsonResponse
    {
        $career->delete();
        return response()->json(['message' => 'Carrera eliminada de forma lógica o física'], 200);
    }
}