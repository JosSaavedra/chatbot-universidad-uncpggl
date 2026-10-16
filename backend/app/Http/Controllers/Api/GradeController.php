<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class GradeController extends Controller
{
    /**
     * Listado de calificaciones.
     *
     * - Un administrador puede ver todas (opcionalmente filtrando por user_id).
     * - Un estudiante SOLO ve las suyas: nunca las de otro usuario, aunque
     *   intente pasar `user_id` en la petición.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Grade::class);

        $user  = $request->user();
        $query = Grade::with('user')->orderBy('period')->orderBy('subject_name');

        if (! $user->is_admin) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json(['data' => $query->get()], 200);
    }

    /**
     * Calificaciones del ESTUDIANTE AUTENTICADO, agrupadas por período.
     *
     * Requiere sesión (Sanctum). El usuario se obtiene EXCLUSIVAMENTE del token:
     * no se acepta `user_id` ni `carnet` por query/body, de modo que es imposible
     * consultar las notas de otro estudiante desde este endpoint.
     */
    public function myGrades(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $student = Student::with(['user', 'career'])
            ->where('user_id', $userId)
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'Tu cuenta no está vinculada a un registro de estudiante.',
            ], 404);
        }

        $grades = Grade::where('user_id', $userId)
            ->orderBy('period')
            ->orderBy('subject_name')
            ->get();

        $periods = $grades
            ->groupBy(fn (Grade $g) => $g->period ?: 'Sin período')
            ->map(function ($items, $period) {
                $subjects = $items->map(fn (Grade $g) => [
                    'id'             => $g->id,
                    'subject_name'   => $g->subject_name,
                    'first_partial'  => $g->first_partial,
                    'second_partial' => $g->second_partial,
                    'final_exam'     => $g->final_exam,
                    'final_grade'    => $g->final_grade,
                    'status'         => $g->status,
                ])->values();

                return [
                    'period'   => (string) $period,
                    'subjects' => $subjects,
                    'summary'  => $this->summarize($items),
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'student' => [
                    'name'          => $student->user?->name,
                    'carnet'        => $student->student_code,
                    'career'        => $student->career?->name,
                    'faculty'       => $student->career?->faculty,
                    'year_of_study' => $student->year_of_study,
                    'group'         => $student->group_code,
                    'status'        => $student->status,
                ],
                'summary' => $this->summarize($grades),
                'periods' => $periods,
            ],
        ], 200);
    }

    /**
     * Resumen académico (aprobadas/reprobadas/pendientes y promedio) de una
     * colección de calificaciones.
     */
    private function summarize($grades): array
    {
        $approved = $grades->where('status', 'approved')->count();
        $failed   = $grades->where('status', 'failed')->count();
        $total    = $grades->count();

        $scores = $grades
            ->pluck('final_grade')
            ->filter(fn ($value) => $value !== null && $value !== '');

        return [
            'total_subjects' => $total,
            'approved'       => $approved,
            'failed'         => $failed,
            'pending'        => $total - $approved - $failed,
            'average'        => $scores->isEmpty()
                ? null
                : round($scores->avg(), 2),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Grade::class);

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
        Gate::authorize('view', $grade);

        return response()->json(['data' => $grade->load('user')], 200);
    }

    public function update(Request $request, Grade $grade): JsonResponse
    {
        Gate::authorize('update', $grade);

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
        Gate::authorize('delete', $grade);

        $grade->delete();
        return response()->json(['message' => 'Registro de nota eliminado'], 200);
    }
}
