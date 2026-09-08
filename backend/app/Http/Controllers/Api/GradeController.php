<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GradeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        
        $query = Grade::with('user');
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json(['data' => $query->get()], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'career_id' => 'required|exists:careers,id',
            'subject_name' => 'required|string|max:255',
            'first_partial' => 'nullable|numeric|between:0,100',
            'second_partial' => 'nullable|numeric|between:0,100',
            'final_exam' => 'nullable|numeric|between:0,100',
            'final_grade' => 'nullable|numeric|between:0,100',
            'status' => 'nullable|in:pending,approved,failed',
            'period' => 'required|string|max:50',
        ]);

        $grade = Grade::create($validated);
        return response()->json(['message' => 'Calificación registrada', 'data' => $grade], 201);
    }

    public function show(Grade $grade): JsonResponse
    {
        return response()->json(['data' => $grade->load('user')], 200);
    }

    public function update(Request $request, Grade $grade): JsonResponse
    {
        $validated = $request->validate([
            'first_partial' => 'nullable|numeric|between:0,100',
            'second_partial' => 'nullable|numeric|between:0,100',
            'final_exam' => 'nullable|numeric|between:0,100',
            'final_grade' => 'nullable|numeric|between:0,100',
            'status' => 'nullable|in:pending,approved,failed',
            'period' => 'string|max:50',
        ]);

        $grade->update($validated);
        return response()->json(['message' => 'Calificación actualizada', 'data' => $grade], 200);
    }

    public function destroy(Grade $grade): JsonResponse
    {
        $grade->delete();
        return response()->json(['message' => 'Registro de nota eliminado'], 200);
    }
}