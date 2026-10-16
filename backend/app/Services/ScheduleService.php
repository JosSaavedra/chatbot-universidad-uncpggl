<?php

namespace App\Services;

use App\Models\Career;
use App\Models\Schedule;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Construye los datos estructurados de horarios que consumen tanto el
 * chatbot (ChatbotService) como el endpoint del módulo de horarios.
 *
 * Centraliza el formato de cada clase y el cálculo de la clase actual y la
 * próxima, para que la tarjeta del chat y la vista completa muestren lo mismo.
 */
class ScheduleService
{
    /** @var array<int, string> */
    public const DAYS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    private const TIMEZONE = 'America/Managua';

    /**
     * Horario personalizado de un estudiante autenticado.
     *
     * @return array<string, mixed>
     */
    public function forStudent(Student $student): array
    {
        $classes = $this->classes(
            (int) $student->career_id,
            (int) $student->year_of_study,
            $student->group_code ? (string) $student->group_code : null
        );

        $career = Career::find($student->career_id);

        return $this->build($classes, [
            'personalized' => true,
            'student'      => [
                'carnet' => $student->student_code,
                'year'   => (int) $student->year_of_study,
                'group'  => $student->group_code,
            ],
            'career_id'    => (int) $student->career_id,
            'career'       => $career?->name,
            'career_code'  => $career?->code,
            'year'         => (int) $student->year_of_study,
            'group'        => $student->group_code,
        ]);
    }

    /**
     * Horario GENERAL (público) para una carrera, año y grupo.
     *
     * @return array<string, mixed>
     */
    public function forGroup(int $careerId, int $year, ?string $group): array
    {
        $classes = $this->classes($careerId, $year, $group);
        $career  = Career::find($careerId);

        return $this->build($classes, [
            'personalized' => false,
            'student'      => null,
            'career_id'    => $careerId,
            'career'       => $career?->name,
            'career_code'  => $career?->code,
            'year'         => $year,
            'group'        => $group,
        ]);
    }

    /**
     * Opciones disponibles para el modo demostración: carreras con sus años y
     * grupos que tienen horarios registrados.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $rows = Schedule::query()
            ->orderBy('career_id')
            ->orderBy('year_of_study')
            ->orderBy('group_code')
            ->get(['career_id', 'year_of_study', 'group_code'])
            ->unique(fn ($r) => $r->career_id . '|' . $r->year_of_study . '|' . $r->group_code)
            ->values();

        $careers = Career::query()
            ->whereIn('id', $rows->pluck('career_id')->unique()->all())
            ->get(['id', 'name', 'code', 'faculty'])
            ->keyBy('id');

        $list = $rows->groupBy('career_id')->map(function ($items, $careerId) use ($careers) {
            $career = $careers->get($careerId);

            $years = $items->groupBy('year_of_study')->map(fn ($group, $year) => [
                'year'   => (int) $year,
                'groups' => $group->pluck('group_code')->filter()->unique()->values()->all(),
            ])->sortBy('year')->values()->all();

            return [
                'id'      => (int) $careerId,
                'name'    => $career?->name,
                'code'    => $career?->code,
                'faculty' => $career?->faculty,
                'years'   => $years,
            ];
        })->values()->all();

        return [
            'demo'    => true,
            'careers' => $list,
        ];
    }

    /**
     * @return Collection<int, Schedule>
     */
    private function classes(int $careerId, int $year, ?string $group): Collection
    {
        return Schedule::query()
            ->with(['teacher', 'sede', 'career'])
            ->where('career_id', $careerId)
            ->where('year_of_study', $year)
            ->when($group !== null && $group !== '', fn ($q) => $q->where('group_code', $group))
            ->orderByRaw("CASE day_of_week
                WHEN 'Lunes' THEN 1 WHEN 'Martes' THEN 2 WHEN 'Miércoles' THEN 3
                WHEN 'Jueves' THEN 4 WHEN 'Viernes' THEN 5 WHEN 'Sábado' THEN 6
                WHEN 'Domingo' THEN 7 ELSE 8 END")
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Da forma a la respuesta final (clases + clase actual + próxima clase).
     *
     * @param  Collection<int, Schedule>  $classes
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function build(Collection $classes, array $meta): array
    {
        $now       = Carbon::now(self::TIMEZONE);
        $todayName = self::DAYS[$now->dayOfWeekIso - 1];
        $nowMin    = $now->hour * 60 + $now->minute;

        $items = $classes->map(function (Schedule $s) use ($todayName, $nowMin) {
            $startMin = $this->toMinutes($s->start_time);
            $endMin   = $this->toMinutes($s->end_time);
            $isToday  = $s->day_of_week === $todayName;

            return [
                'id'         => $s->id,
                'subject'    => $s->subject_name,
                'day'        => $s->day_of_week,
                'start_time' => $this->formatTime($s->start_time),
                'end_time'   => $this->formatTime($s->end_time),
                'teacher'    => $s->teacher_display_name,
                'classroom'  => $s->classroom,
                'building'   => $s->building,
                'sede'       => $s->sede?->name,
                'career'     => $s->career?->name,
                'year'       => (int) $s->year_of_study,
                'group'      => $s->group_code,
                'period'     => $s->academic_period,
                'is_demo'    => (bool) $s->is_demo,
                'is_today'   => $isToday,
                'is_current' => $isToday && $nowMin >= $startMin && $nowMin < $endMin,
                'is_next'    => false,
            ];
        })->values()->all();

        $current = null;
        foreach ($items as $item) {
            if ($item['is_current']) {
                $current = $item;
                break;
            }
        }

        $next = $this->findNext($items, $todayName, $nowMin);

        // Marca sólo una vez la próxima clase.
        if ($next !== null) {
            $items = array_map(function ($item) use ($next) {
                if ($item['id'] === $next['id']) {
                    $item['is_next'] = true;
                }

                return $item;
            }, $items);
        }

        $week = [];
        foreach (self::DAYS as $day) {
            $week[] = [
                'day'   => $day,
                'items' => array_values(array_filter($items, fn ($i) => $i['day'] === $day)),
            ];
        }

        return array_merge($meta, [
            'demo'          => true,
            'today'         => $todayName,
            'server_time'   => $now->format('H:i'),
            'empty'         => count($items) === 0,
            'current_class' => $current,
            'next_class'    => $next,
            'classes'       => $items,
            'week'          => $week,
        ]);
    }

    /**
     * Encuentra la próxima clase: primero las que quedan hoy y, si no hay,
     * la primera de los días siguientes (con vuelta a la semana).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function findNext(array $items, string $todayName, int $nowMin): ?array
    {
        $after = array_values(array_filter(
            $items,
            fn ($i) => $i['day'] === $todayName && $this->toMinutes($i['start_time']) > $nowMin
        ));

        if ($after !== []) {
            usort($after, fn ($a, $b) => $this->toMinutes($a['start_time']) <=> $this->toMinutes($b['start_time']));

            return $after[0];
        }

        $startIdx = array_search($todayName, self::DAYS, true);
        if ($startIdx === false) {
            return null;
        }

        for ($k = 1; $k <= count(self::DAYS); $k++) {
            $day      = self::DAYS[($startIdx + $k) % count(self::DAYS)];
            $dayItems = array_values(array_filter($items, fn ($i) => $i['day'] === $day));

            if ($dayItems !== []) {
                usort($dayItems, fn ($a, $b) => $this->toMinutes($a['start_time']) <=> $this->toMinutes($b['start_time']));

                return $dayItems[0];
            }
        }

        return null;
    }

    private function formatTime(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return substr($time, 0, 5);
    }

    private function toMinutes(?string $time): int
    {
        if (! $time) {
            return 0;
        }

        $parts = explode(':', substr($time, 0, 5));

        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }
}
