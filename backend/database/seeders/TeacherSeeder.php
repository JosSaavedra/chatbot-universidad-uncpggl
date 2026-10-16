<?php

namespace Database\Seeders;

use App\Models\Career;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

/**
 * Docentes FICTICIOS de ejemplo para el prototipo de la tesis.
 *
 * Se generan de forma determinista por facultad para que coincidan con las
 * carreras. Todos se marcan con is_demo = true (datos de demostración, no
 * oficiales). Idempotente: si ya hay docentes, no vuelve a insertar.
 */
class TeacherSeeder extends Seeder
{
    /** @var array<int, string> */
    private const FIRST_NAMES = [
        'Roberto', 'Carmen', 'Manuel', 'Javier', 'Ernesto', 'Luis', 'Patricia',
        'Sandra', 'María', 'José', 'Ana', 'Carlos', 'Martha', 'Julio', 'Elena',
        'Ramón', 'Claudia', 'Óscar', 'Silvia', 'Wilmer', 'Karla', 'Héctor',
        'Norma', 'Francisco', 'Ivette', 'Denis', 'Rosa', 'Marvin', 'Gloria',
        'Allan',
    ];

    /** @var array<int, string> */
    private const LAST_NAMES = [
        'Chavarría', 'López', 'Sánchez', 'Obando', 'Báez', 'Centeno', 'Vega',
        'Potosme', 'Martínez', 'Guzmán', 'Ríos', 'Morales', 'Castillo',
        'Espinoza', 'Hernández', 'Rodríguez', 'Mendoza', 'Gutiérrez', 'Ortega',
        'Ramírez', 'Zelaya', 'Blandón', 'Amador', 'Cáceres', 'Rivas',
        'Montenegro', 'Aguilar', 'Sequeira', 'Palacios', 'Urbina',
    ];

    /** @var array<int, string> */
    private const TITLES_MASCULINE = ['Dr.', 'Ing.', 'Lic.', 'MSc.'];

    /** @var array<int, string> */
    private const TITLES_FEMININE = ['Dra.', 'Ing.', 'Lic.', 'MSc.'];

    /** Tratamientos coherentes con cada facultad. */
    private const TITLES_BY_FACULTY = [
        'Ciencias de la Salud'                 => ['Dr.', 'Dra.', 'Lic.', 'MSc.'],
        'Arquitectura e Ingeniería'            => ['Ing.', 'Arq.', 'MSc.', 'Dr.', 'Dra.'],
        'Ciencias y Tecnología'                => ['Ing.', 'MSc.', 'Dr.', 'Dra.'],
        'Ciencias Agropecuarias'               => ['Ing.', 'MSc.', 'Dr.', 'Dra.'],
        'Ciencias Económicas y Administrativas' => ['Lic.', 'MSc.', 'Dr.', 'Dra.'],
    ];

    /** @var array<int, string> Nombres femeninos (para concordar el tratamiento). */
    private const FEMININE_NAMES = [
        'Carmen', 'Patricia', 'Sandra', 'María', 'Ana', 'Martha', 'Elena',
        'Claudia', 'Silvia', 'Karla', 'Norma', 'Ivette', 'Rosa', 'Gloria',
    ];

    private const PER_FACULTY = 14;

    public function run(): void
    {
        if (Teacher::query()->exists()) {
            return;
        }

        $faculties = Career::query()
            ->whereNotNull('faculty')
            ->distinct()
            ->orderBy('faculty')
            ->pluck('faculty')
            ->values();

        if ($faculties->isEmpty()) {
            return;
        }

        $rows = [];
        foreach ($faculties as $f => $faculty) {
            for ($i = 0; $i < self::PER_FACULTY; $i++) {
                $first = self::FIRST_NAMES[($f * 3 + $i) % count(self::FIRST_NAMES)];
                $last1 = self::LAST_NAMES[($f * 5 + $i) % count(self::LAST_NAMES)];
                $last2 = self::LAST_NAMES[($f * 7 + $i + 3) % count(self::LAST_NAMES)];

                $titles = self::TITLES_BY_FACULTY[$faculty] ?? self::TITLES_MASCULINE;
                $title = $titles[($f + $i) % count($titles)];

                $isFeminine = in_array($first, self::FEMININE_NAMES, true);
                if ($title === 'Dr.' && $isFeminine) {
                    $title = 'Dra.';
                } elseif ($title === 'Dra.' && ! $isFeminine) {
                    $title = 'Dr.';
                }

                $rows[] = [
                    'full_name'  => "{$title} {$first} {$last1} {$last2}",
                    'email'      => 'docente.' . ($f + 1) . '.' . ($i + 1) . '@uncpggl.edu.ni',
                    'phone'      => '2' . str_pad((string) (7000000 + $f * 100000 + $i * 137), 7, '0', STR_PAD_LEFT),
                    'area'       => $faculty,
                    'title'      => $title,
                    'is_demo'    => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        Teacher::query()->insert($rows);
    }
}
