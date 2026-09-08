<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        
        $query = Schedule::with('career');
        if ($request->has('career_id')) {
            $query->where('career_id', $request->career_id);
        }
        
        return response()->json(['data' => $query->get()], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'career_id' => 'required|exists:careers,id',
            'subject_name' => 'required|string|max:255',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday,Lunes,Martes,Miércoles,Jueves,Viernes,Sábado,Domingo',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'classroom' => 'nullable|string|max:50',
            'teacher_name' => 'nullable|string|max:255',
            'year_of_study' => 'required|integer|min:1',
        ]);

        $schedule = Schedule::create($validated);
        return response()->json(['message' => 'Horario agendado', 'data' => $schedule], 201);
    }

    public function show(Schedule $schedule): JsonResponse
    {
        return response()->json(['data' => $schedule->load('career')], 200);
    }

    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        $validated = $request->validate([
            'subject_name' => 'string|max:255',
            'day_of_week' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday,Lunes,Martes,Miércoles,Jueves,Viernes,Sábado,Domingo',
            'start_time' => 'date_format:H:i',
            'end_time' => 'date_format:H:i|after:start_time',
            'classroom' => 'nullable|string|max:50',
            'teacher_name' => 'nullable|string|max:255',
            'year_of_study' => 'integer|min:1',
        ]);

        $schedule->update($validated);
        return response()->json(['message' => 'Horario actualizado', 'data' => $schedule], 200);
    }

    public function destroy(Schedule $schedule): JsonResponse
    {
        $schedule->delete();
        return response()->json(['message' => 'Bloque de horario eliminado'], 200);
    }
}