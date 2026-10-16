<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UniversityEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class UniversityEventController extends Controller
{
    /**
     * Lista pública de eventos universitarios.
     * Soporta filtros por rango de fechas, categoría, carrera y alcance.
     *
     * GET /api/university-events?start=2026-10-01&end=2026-10-31&category=exam&career_id=3
     */
    public function index(Request $request): JsonResponse
    {
        $query = UniversityEvent::with('career:id,name,code')
            ->where('is_active', true);

        if ($request->filled('start') && $request->filled('end')) {
            $start = $request->input('start');
            $end   = $request->input('end');

            $query->where('start_date', '<=', $end . ' 23:59:59')
                  ->whereRaw('COALESCE(end_date, start_date) >= ?', [$start . ' 00:00:00']);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('career_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('career_id', $request->input('career_id'))
                  ->orWhere('target_scope', 'all');
            });
        }

        if ($request->filled('target_scope')) {
            $query->where('target_scope', $request->input('target_scope'));
        }

        return response()->json(
            $query->orderBy('start_date')->get()
        );
    }

    /**
     * Detalle público de un evento.
     */
    public function show($id): JsonResponse
    {
        $event = UniversityEvent::with('career:id,name,code')->findOrFail($id);

        return response()->json($event);
    }

    /**
     * Crea un evento (solo administradores, protegido por middleware "admin").
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateEvent($request);

        $event = UniversityEvent::create($data);
        $event->load('career:id,name,code');

        return response()->json($event, 201);
    }

    /**
     * Actualiza un evento (solo administradores).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $event = UniversityEvent::findOrFail($id);

        $data = $this->validateEvent($request, $event->id);

        $event->update($data);
        $event->load('career:id,name,code');

        return response()->json($event);
    }

    /**
     * Elimina un evento (solo administradores).
     */
    public function destroy($id): JsonResponse
    {
        $event = UniversityEvent::findOrFail($id);
        $event->delete();

        return response()->json(['message' => 'Evento eliminado correctamente.']);
    }

    /**
     * Devuelve las categorías disponibles con su etiqueta y color.
     * GET /api/university-events/categories
     */
    public function categories(): JsonResponse
    {
        $categories = collect(config('calendar.categories', []))
            ->map(fn ($meta, $key) => [
                'value' => $key,
                'label' => $meta['label'] ?? $key,
                'color' => $meta['color'] ?? '#64748b',
            ])
            ->values();

        return response()->json($categories);
    }

    private function validateEvent(Request $request, ?int $ignoreId = null): array
    {
        $categories = array_keys(config('calendar.categories', []));

        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'start_date'      => 'required|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'category'        => ['required', 'string', Rule::in($categories)],
            'career_id'       => 'nullable|required_if:target_scope,specific|integer|exists:careers,id',
            'campus'          => 'nullable|string|max:255',
            'academic_period' => 'nullable|string|max:255',
            'location'        => 'nullable|string|max:255',
            'organizer'       => 'nullable|string|max:255',
            'target_scope'    => 'required|in:all,specific',
            'is_active'       => 'sometimes|boolean',
        ]);

        // Si el evento es para todos, no debe quedar ligado a una carrera.
        if (($data['target_scope'] ?? 'all') === 'all') {
            $data['career_id'] = null;
        }

        return $data;
    }
}
