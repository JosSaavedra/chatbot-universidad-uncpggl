<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scholarship;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ScholarshipController extends Controller
{
    public function index(): JsonResponse
    {
        
        $scholarships = Scholarship::with('user')->get();
        return response()->json(['data' => $scholarships], 200);
    }

    public function store(Request $request): JsonResponse
    {
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
        return response()->json(['data' => $scholarship->load('user')], 200);
    }

    public function update(Request $request, Scholarship $scholarship): JsonResponse
    {
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
        $scholarship->delete();
        return response()->json(['message' => 'Registro de beca eliminado'], 200);
    }
}