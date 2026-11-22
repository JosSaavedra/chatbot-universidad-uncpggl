<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ScheduleController extends Controller
{
    public function __construct(protected ScheduleService $scheduleService) {}

    public function index(Request $request): JsonResponse
    {
        // Los bloques de horario no contienen datos personales del estudiante:
        // su consulta está disponible para cualquier usuario autenticado.
        Gate::authorize('viewAny', Schedule::class);

        $query = Schedule::with(['career', 'teacher:id,full_name', 'sede']);

        if ($request->filled('career_id')) {
            $query->where('career_id', $request->input('career_id'));
        }
        if ($request->filled('year')) {
            $query->where('year_of_study', $request->input('year'));
        }
        if ($request->filled('group')) {
            $query->where('group_code', $request->input('group'));
        }

        return response()->json(['data' => $query->get()], 200);
    }

    /**
     * Catálogo público para el modo demostración: carreras, años y grupos con
     * horarios registrados. No requiere autenticación.
     */
    public function options(): JsonResponse
    {
        return response()->json(['data' => $this->scheduleService->options()], 200);
    }

    /**
     * Horario GENERAL (público) por carrera, año y grupo. No expone información
     * personal de ningún estudiante.
     */
    public function general(Request $request): JsonResponse
    {
        $data = $request->validate([
            'career_id' => 'required|integer|exists:careers,id',
            'year'      => 'required|integer|min:1|max:8',
            'group'     => 'nullable|string|max:5',
        ]);

        $schedule = $this->scheduleService->forGroup(
            (int) $data['career_id'],
            (int) $data['year'],
            $data['group'] ?? null,
        );

        return response()->json(['data' => $schedule], 200);
    }

    /**
     * Horario personalizado del estudiante autenticado. Requiere sesión y sólo
     * devuelve la información del propio estudiante.
     */
    public function mySchedule(Request $request): JsonResponse
    {
        // La identidad se toma EXCLUSIVAMENTE del token de Sanctum: no se
        // acepta ningún `user_id` ni carné enviado por el cliente.
        $user = $request->user();
        abort_if($user === null, 401);

        $student = Student::where('user_id', $user->id)->first();

        if (! $student) {
            return response()->json([
                'message' => 'Esta cuenta no tiene un perfil de estudiante asociado.',
            ], 404);
        }

        return response()->json(['data' => $this->scheduleService->forStudent($student)], 200);
    }

    public function store(Request $request): JsonResponse
    {
        // Alta de bloques de horario: exclusiva de administradores.
        Gate::authorize('create', Schedule::class);

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
        // Bloque de horario: visible para cualquier usuario autenticado.
        Gate::authorize('view', $schedule);

        return response()->json(['data' => $schedule->load(['career', 'teacher:id,full_name', 'sede'])], 200);
    }

    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        // Modificar horarios administrativos: exclusivo de administradores.
        Gate::authorize('update', $schedule);

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
        // Borrado de bloques de horario: exclusivo de administradores.
        Gate::authorize('delete', $schedule);

        $schedule->delete();
        return response()->json(['message' => 'Bloque de horario eliminado'], 200);
    }
}
