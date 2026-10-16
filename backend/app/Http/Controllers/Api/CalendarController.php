<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UniversityEvent;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CalendarController extends Controller
{
    /**
     * Zona horaria oficial de Nicaragua.
     */
    private const TIMEZONE = 'America/Managua';

    public function __construct(private HolidayService $holidays) {}

    /**
     * GET /api/calendar?start=2026-10-01&end=2026-10-31
     *
     * Devuelve en una sola respuesta los feriados de Nicaragua y los eventos
     * universitarios registrados en la base de datos dentro del rango pedido.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start'     => 'required|date',
            'end'       => 'required|date|after_or_equal:start',
            'career_id' => 'nullable|integer|exists:careers,id',
            'category'  => 'nullable|string',
        ]);

        $start = Carbon::parse($data['start'], self::TIMEZONE)->startOfDay();
        $end   = Carbon::parse($data['end'], self::TIMEZONE)->endOfDay();

        return response()->json([
            'range'    => [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
            ],
            'timezone' => self::TIMEZONE,
            'holidays' => $this->getHolidays($start, $end),
            'events'   => $this->getUniversityEvents($start, $end, $request),
        ]);
    }

    /**
     * GET /api/calendar/holidays?year=2026  (o ?start=&end=)
     * Solo devuelve los feriados nacionales de Nicaragua.
     */
    public function holidays(Request $request): JsonResponse
    {
        if ($request->filled('year')) {
            $year  = (int) $request->input('year');
            $start = Carbon::create($year, 1, 1, 0, 0, 0, self::TIMEZONE)->startOfDay();
            $end   = Carbon::create($year, 12, 31, 23, 59, 59, self::TIMEZONE)->endOfDay();
        } else {
            $data  = $request->validate([
                'start' => 'required|date',
                'end'   => 'required|date|after_or_equal:start',
            ]);
            $start = Carbon::parse($data['start'], self::TIMEZONE)->startOfDay();
            $end   = Carbon::parse($data['end'], self::TIMEZONE)->endOfDay();
        }

        return response()->json([
            'holidays' => $this->getHolidays($start, $end),
        ]);
    }

    /**
     * Obtiene los feriados de Nicaragua (traducidos al español) dentro del rango.
     */
    private function getHolidays(Carbon $start, Carbon $end): array
    {
        $holidays = $this->holidays->getHolidays($start, $end);

        return collect($holidays)
            ->map(function ($h) {
                $date = Carbon::parse($h['date'], self::TIMEZONE);

                return [
                    'id'             => 'holiday_' . $h['date'] . '_' . md5($h['name']),
                    'title'          => $h['name'],
                    'start'          => $date->toDateString(),
                    'end'            => $date->toDateString(),
                    'allDay'         => true,
                    'category'       => 'holiday',
                    'type'           => 'holiday',
                    'color'          => $this->categoryColor('holiday'),
                    'category_label' => $this->categoryLabel('holiday'),
                    'description'    => $h['name'],
                    'source'         => 'nager.date',
                ];
            })
            ->unique('id')
            ->sortBy('start')
            ->values()
            ->all();
    }

    /**
     * Devuelve los eventos universitarios activos dentro del rango de fechas.
     * Soporta filtros opcionales por carrera y por categoría.
     */
    private function getUniversityEvents(Carbon $start, Carbon $end, Request $request): array
    {
        $query = UniversityEvent::with('career:id,name,code')
            ->where('is_active', true)
            // Un evento se incluye si su rango [start_date, end_date] se solapa con [start, end].
            ->where('start_date', '<=', $end)
            // COALESCE cubre los eventos sin fecha de fin (evento de un solo día).
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$start->toDateTimeString()]);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('career_id')) {
            // Eventos de la carrera solicitada o eventos dirigidos a todos.
            $query->where(function ($q) use ($request) {
                $q->where('career_id', $request->input('career_id'))
                  ->orWhere('target_scope', 'all');
            });
        }

        return $query->orderBy('start_date')
            ->get()
            ->map(function (UniversityEvent $event) {
                $startDate = Carbon::parse($event->start_date, self::TIMEZONE);
                $endDate   = $event->end_date
                    ? Carbon::parse($event->end_date, self::TIMEZONE)
                    : $startDate->copy()->endOfDay();

                $isAllDay = $startDate->format('H:i') === '00:00'
                    && in_array($endDate->format('H:i'), ['23:59', '00:00'], true);

                return [
                    'id'              => $event->id,
                    'title'           => $event->title,
                    'start'           => $startDate->toIso8601String(),
                    'end'             => $endDate->toIso8601String(),
                    'allDay'          => $isAllDay,
                    'category'        => $event->category,
                    'type'            => $event->category,
                    'color'           => $this->categoryColor($event->category),
                    'category_label'  => $this->categoryLabel($event->category),
                    'description'     => $event->description,
                    'location'        => $event->location,
                    'organizer'       => $event->organizer,
                    'campus'          => $event->campus,
                    'academic_period' => $event->academic_period,
                    'target_scope'    => $event->target_scope,
                    'career'          => $event->career,
                    'source'          => 'database',
                ];
            })
            ->all();
    }

    private function categoryColor(?string $category): string
    {
        $category = $category ?: 'other';

        return config("calendar.categories.{$category}.color", config('calendar.categories.other.color', '#0891b2'));
    }

    private function categoryLabel(?string $category): string
    {
        $category = $category ?: 'other';

        return config("calendar.categories.{$category}.label", 'Otros eventos');
    }
}
