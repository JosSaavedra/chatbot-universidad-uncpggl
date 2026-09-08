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
        $careers = Career::where('is_active', true)->get();
        return response()->json(['data' => $careers], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:careers,code',
            'name' => 'required|string|max:255',
            'faculty' => 'nullable|string|max:255',
            'duration_years' => 'required|integer|min:1',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'monthly_fee' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $career = Career::create($validated);
        return response()->json(['message' => 'Carrera creada con éxito', 'data' => $career], 201);
    }

    public function show(Career $career): JsonResponse
    {
        return response()->json(['data' => $career], 200);
    }

    public function update(Request $request, Career $career): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'string|unique:careers,code,' . $career->id,
            'name' => 'string|max:255',
            'faculty' => 'nullable|string|max:255',
            'duration_years' => 'integer|min:1',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'monthly_fee' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
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