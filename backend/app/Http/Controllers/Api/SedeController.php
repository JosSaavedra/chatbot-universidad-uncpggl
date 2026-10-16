<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class SedeController extends Controller
{
    /**
     * Lista pública de sedes con las carreras que ofrece cada una.
     *
     * GET /api/sedes
     * GET /api/sedes?career_id=3   (solo sedes que ofrecen esa carrera)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sede::with(['careers' => function ($q) {
            $q->where('careers.is_active', true)->orderBy('careers.name');
        }])->where('is_active', true);

        if ($request->filled('career_id')) {
            $query->whereHas('careers', function ($q) use ($request) {
                $q->where('careers.id', $request->input('career_id'));
            });
        }

        $sedes = $query->orderByDesc('is_main')->orderBy('name')->get();

        return response()->json([
            'data' => $sedes->map(fn (Sede $s) => $this->transform($s))->values(),
            'meta' => [
                'total'          => $sedes->count(),
                'careers_total'  => Career::where('is_active', true)->count(),
            ],
        ]);
    }

    /**
     * Detalle público de una sede.
     */
    public function show($id): JsonResponse
    {
        $sede = Sede::with(['careers' => function ($q) {
            $q->where('careers.is_active', true)->orderBy('careers.name');
        }])->findOrFail($id);

        return response()->json($this->transform($sede));
    }

    /**
     * Crea una sede (solo administradores).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateSede($request);
        $careers = $data['careers'] ?? [];
        unset($data['careers']);

        $sede = Sede::create($data);
        $this->syncCareers($sede, $careers);

        return response()->json($this->transform($sede->load('careers')), 201);
    }

    /**
     * Actualiza una sede (solo administradores).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $sede = Sede::findOrFail($id);

        $data = $this->validateSede($request, $sede->id);
        $careers = $data['careers'] ?? null;
        unset($data['careers']);

        $sede->update($data);

        if ($careers !== null) {
            $this->syncCareers($sede, $careers);
        }

        return response()->json($this->transform($sede->load('careers')));
    }

    /**
     * Elimina una sede (solo administradores).
     */
    public function destroy($id): JsonResponse
    {
        $sede = Sede::findOrFail($id);
        $sede->careers()->detach();
        $sede->delete();

        return response()->json(['message' => 'Sede eliminada correctamente.']);
    }

    private function validateSede(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'     => ['required', 'string', 'max:255', Rule::unique('sedes', 'name')->ignore($ignoreId)],
            'code'     => 'nullable|string|max:255',
            'address'  => 'required|string|max:255',
            'city'     => 'nullable|string|max:255',
            'phone'    => 'nullable|string|max:50',
            'is_main'  => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'careers'  => 'sometimes|array',
            'careers.*' => 'integer|exists:careers,id',
        ]);
    }

    private function syncCareers(Sede $sede, array $careerIds): void
    {
        $sede->careers()->sync($careerIds);
    }

    private function transform(Sede $sede): array
    {
        return [
            'id'            => $sede->id,
            'name'          => $sede->name,
            'code'          => $sede->code,
            'address'       => $sede->address,
            'city'          => $sede->city,
            'phone'         => $sede->phone,
            'is_main'       => $sede->is_main,
            'is_active'     => $sede->is_active,
            'careers_count' => $sede->careers->count(),
            'careers'       => $sede->careers->map(fn (Career $c) => [
                'id'      => $c->id,
                'name'    => $c->name,
                'code'    => $c->code,
                'faculty' => $c->faculty,
            ])->values(),
        ];
    }
}
