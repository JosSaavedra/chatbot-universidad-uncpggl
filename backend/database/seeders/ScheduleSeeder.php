<?php

namespace Database\Seeders;

use App\Models\Career;
use App\Models\CareerSubject;
use App\Models\Schedule;
use App\Models\Sede;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

/**
 * Horarios de clases de ejemplo para el prototipo de la tesis.
 *
 * Genera, de forma determinista, un horario semanal completo para cada
 * combinación (carrera, año, grupo) usando docentes FICTICIOS, aulas,
 * edificios y sedes. Los registros se marcan con is_demo = true.
 *
 * Garantías de coherencia:
 *  - Un mismo grupo nunca tiene dos clases simultáneas (cada asignatura cae en
 *    un día distinto).
 *  - Un mismo docente no está en dos aulas a la misma hora.
 *  - Un mismo aula no se reserva para dos grupos a la misma hora.
 *
 * Idempotente: si ya existen horarios de demostración, no vuelve a insertar.
 * No elimina ni modifica horarios reales existentes.
 */
class ScheduleSeeder extends Seeder
{
    /** @var array<int, array{0: string, 1: string}> */
    private const SLOTS = [
        ['07:00', '09:00'],
        ['09:00', '11:00'],
        ['11:00', '13:00'],
        ['13:00', '15:00'],
        ['15:00', '17:00'],
    ];

    /** @var array<int, string> */
    private const DAYS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

    /** @var array<int, string> */
    private const GROUPS = ['A', 'B'];

    /** @var array<int, string> */
    private const GENERIC_SUBJECTS = [
        'Metodología de la Investigación',
        'Ética Profesional',
        'Comunicación y Lenguaje',
        'Matemáticas Aplicadas',
        'Tecnologías de la Información',
        'Educación Ambiental',
    ];

    private const ACADEMIC_PERIOD = 'I-2026';

    public function run(): void
    {
        // 1) Asignar grupo a los estudiantes que aún no lo tengan (sin borrar datos).
        $this->assignStudentGroups();

        // 2) Evitar duplicar los horarios de demostración.
        if (Schedule::query()->where('is_demo', true)->exists()) {
            return;
        }

        $careers = Career::query()->where('is_active', true)->get();
        if ($careers->isEmpty()) {
            return;
        }

        $teachers = Teacher::query()->get();
        if ($teachers->isEmpty()) {
            return;
        }

        $teachersByArea = $teachers->groupBy('area');
        $sedeId = Sede::query()->where('code', 'CUR-LEON')->value('id');

        // Asignaturas por carrera y año (pensum).
        $subjectsByCareerYear = CareerSubject::query()
            ->orderBy('name')
            ->get(['career_id', 'year', 'name'])
            ->groupBy(fn ($s) => $s->career_id . '|' . $s->year);

        // Ocupación por día y franja horaria.
        $teacherBusy = [];
        $roomBusy    = [];

        $rooms = $this->rooms();

        $rows = [];
        $comboIndex = 0;

        foreach ($careers as $career) {
            $years = max(1, (int) $career->duration_years);

            for ($year = 1; $year <= $years; $year++) {
                $subjects = $this->subjectsFor($subjectsByCareerYear, $career->id, $year);

                foreach (self::GROUPS as $group) {
                    foreach ($subjects as $i => $subject) {
                        $day       = self::DAYS[$i % count(self::DAYS)];
                        $slotIndex = ($comboIndex + $i) % count(self::SLOTS);
                        [$start, $end] = self::SLOTS[$slotIndex];

                        $cell = $day . '|' . $slotIndex;

                        $teacher = $this->pickTeacher(
                            $teachers,
                            $teachersByArea,
                            $career->faculty,
                            $cell,
                            $teacherBusy,
                            $comboIndex * 3 + $i
                        );

                        $room = $this->pickRoom($rooms, $cell, $roomBusy);

                        $rows[] = [
                            'career_id'       => $career->id,
                            'teacher_id'      => $teacher->id,
                            'subject_name'    => $subject,
                            'teacher_name'    => $teacher->full_name,
                            'day_of_week'     => $day,
                            'start_time'      => $start,
                            'end_time'        => $end,
                            'classroom'       => $room['code'],
                            'building'        => $room['building'],
                            'year_of_study'   => $year,
                            'group_code'      => $group,
                            'sede_id'         => $sedeId,
                            'academic_period' => self::ACADEMIC_PERIOD,
                            'is_demo'         => true,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ];
                    }

                    $comboIndex++;
                }
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Schedule::query()->insert($chunk);
        }
    }

    /**
     * Asigna un grupo a los estudiantes que no tengan uno (A/B alternado).
     */
    private function assignStudentGroups(): void
    {
        $students = Student::query()->whereNull('group_code')->orderBy('id')->get();

        foreach ($students as $i => $student) {
            $student->group_code = self::GROUPS[$i % count(self::GROUPS)];
            $student->save();
        }
    }

    /**
     * Devuelve hasta 5 asignaturas del pensum para una carrera y año, rellenando
     * con asignaturas genéricas si el pensum tiene menos.
     *
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, CareerSubject>>  $subjectsByCareerYear
     * @return array<int, string>
     */
    private function subjectsFor($subjectsByCareerYear, int $careerId, int $year): array
    {
        $subjects = $subjectsByCareerYear
            ->get($careerId . '|' . $year, collect())
            ->pluck('name')
            ->unique()
            ->take(5)
            ->values()
            ->all();

        $i = 0;
        while (count($subjects) < 5 && $i < count(self::GENERIC_SUBJECTS)) {
            $candidate = self::GENERIC_SUBJECTS[$i++];
            if (! in_array($candidate, $subjects, true)) {
                $subjects[] = $candidate;
            }
        }

        return array_slice($subjects, 0, 5);
    }

    /**
     * Elige un docente libre en la franja; prioriza los de la misma facultad.
     *
     * @param  \Illuminate\Support\Collection<int, Teacher>  $teachers
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Teacher>>  $teachersByArea
     * @param  array<string, array<int, int>>  $teacherBusy
     */
    private function pickTeacher($teachers, $teachersByArea, ?string $faculty, string $cell, array &$teacherBusy, int $rotation = 0): Teacher
    {
        $busy = $teacherBusy[$cell] ?? [];

        $preferred = $faculty ? ($teachersByArea->get($faculty) ?? collect())->values() : collect();

        foreach ([$preferred, $teachers->values()] as $pool) {
            $count = $pool->count();
            if ($count === 0) {
                continue;
            }

            for ($k = 0; $k < $count; $k++) {
                $teacher = $pool[($rotation + $k) % $count];
                if (! in_array($teacher->id, $busy, true)) {
                    $teacherBusy[$cell][] = $teacher->id;

                    return $teacher;
                }
            }
        }

        // Respaldo (no debería ocurrir con suficientes docentes): reutiliza uno.
        $fallback = $preferred->first() ?? $teachers->first();
        $teacherBusy[$cell][] = $fallback->id;

        return $fallback;
    }

    /**
     * Elige un aula libre en la franja horaria.
     *
     * @param  array<int, array{code: string, building: string}>  $rooms
     * @param  array<string, array<int, string>>  $roomBusy
     * @return array{code: string, building: string}
     */
    private function pickRoom(array $rooms, string $cell, array &$roomBusy): array
    {
        $busy = $roomBusy[$cell] ?? [];

        foreach ($rooms as $room) {
            if (! in_array($room['code'], $busy, true)) {
                $roomBusy[$cell][] = $room['code'];

                return $room;
            }
        }

        // Respaldo (no debería ocurrir con suficientes aulas).
        $fallback = $rooms[0];
        $roomBusy[$cell][] = $fallback['code'];

        return $fallback;
    }

    /**
     * Catálogo de aulas/espacios con su edificio.
     *
     * @return array<int, array{code: string, building: string}>
     */
    private function rooms(): array
    {
        $rooms = [];

        $buildings = [
            'A' => 'Edificio A',
            'B' => 'Edificio B',
            'C' => 'Edificio C',
            'D' => 'Edificio D',
        ];

        foreach ($buildings as $letter => $building) {
            for ($n = 1; $n <= 10; $n++) {
                $rooms[] = [
                    'code'     => sprintf('%s-%d%02d', $letter, 1 + intdiv($n - 1, 4), (($n - 1) % 4) + 1),
                    'building' => $building,
                ];
            }
        }

        foreach ([
            'Lab. Informática 1',
            'Lab. Informática 2',
            'Lab. Redes',
            'Lab. Ciencias 1',
            'Lab. Ciencias 2',
            'Lab. Electrónica',
        ] as $lab) {
            $rooms[] = ['code' => $lab, 'building' => 'Laboratorios'];
        }

        return $rooms;
    }
}
