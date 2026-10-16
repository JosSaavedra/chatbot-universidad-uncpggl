<?php

namespace Database\Seeders;

use App\Models\UniversityEvent;
use Illuminate\Database\Seeder;

/**
 * Eventos académicos de ejemplo para el calendario de la UNCPGGL.
 *
 * NOTA: Estas fechas son de ejemplo (placeholder) para que el calendario se
 * vea completo. El administrador puede editarlas o eliminarlas desde el panel
 * de administración con las fechas reales de la universidad.
 *
 * Este seeder es idempotente: usa firstOrCreate por título, así que puede
 * ejecutarse varias veces sin duplicar registros ni borrar datos existentes.
 */
class UniversityEventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            [
                'title'           => 'Matrícula Académica 2026',
                'description'     => 'Período de matrícula e inscripción para el año académico 2026. Todos los días del 10 al 24 de enero.',
                'start_date'      => '2026-01-10 00:00:00',
                'end_date'        => '2026-01-24 23:59:59',
                'category'        => 'inscription',
                'target_scope'    => 'all',
                'campus'          => 'Campus Central',
                'academic_period' => 'I Cuatrimestre 2026',
                'organizer'       => 'Registro Académico',
            ],
            [
                'title'           => 'Inicio del I Cuatrimestre 2026',
                'description'     => 'Comienzo de clases del I Cuatrimestre 2026.',
                'start_date'      => '2026-01-26 00:00:00',
                'end_date'        => '2026-01-26 23:59:59',
                'category'        => 'term',
                'target_scope'    => 'all',
                'academic_period' => 'I Cuatrimestre 2026',
            ],
            [
                'title'           => 'Exámenes Parciales I Cuatrimestre',
                'description'     => 'Aplicación de exámenes parciales del I Cuatrimestre 2026.',
                'start_date'      => '2026-04-20 00:00:00',
                'end_date'        => '2026-04-24 23:59:59',
                'category'        => 'exam',
                'target_scope'    => 'all',
                'academic_period' => 'I Cuatrimestre 2026',
            ],
            [
                'title'           => 'Cierre del I Cuatrimestre 2026',
                'description'     => 'Último día de clases y cierre del I Cuatrimestre 2026.',
                'start_date'      => '2026-04-30 00:00:00',
                'end_date'        => '2026-04-30 23:59:59',
                'category'        => 'term',
                'target_scope'    => 'all',
                'academic_period' => 'I Cuatrimestre 2026',
            ],
            [
                'title'           => 'Vacaciones Estudiantiles de Medio Año',
                'description'     => 'Receso académico entre el I y el II Cuatrimestre.',
                'start_date'      => '2026-05-01 00:00:00',
                'end_date'        => '2026-05-15 23:59:59',
                'category'        => 'vacation',
                'target_scope'    => 'all',
                'campus'          => 'Campus Central',
            ],
            [
                'title'           => 'Inicio del II Cuatrimestre 2026',
                'description'     => 'Comienzo de clases del II Cuatrimestre 2026.',
                'start_date'      => '2026-05-18 00:00:00',
                'end_date'        => '2026-05-18 23:59:59',
                'category'        => 'term',
                'target_scope'    => 'all',
                'academic_period' => 'II Cuatrimestre 2026',
            ],
            [
                'title'           => 'Exámenes Finales II Cuatrimestre',
                'description'     => 'Aplicación de exámenes finales del II Cuatrimestre 2026.',
                'start_date'      => '2026-09-07 00:00:00',
                'end_date'        => '2026-09-11 23:59:59',
                'category'        => 'exam',
                'target_scope'    => 'all',
                'academic_period' => 'II Cuatrimestre 2026',
            ],
            [
                'title'           => 'Cierre del II Cuatrimestre 2026',
                'description'     => 'Último día de clases y cierre del II Cuatrimestre 2026.',
                'start_date'      => '2026-09-30 00:00:00',
                'end_date'        => '2026-09-30 23:59:59',
                'category'        => 'term',
                'target_scope'    => 'all',
                'academic_period' => 'II Cuatrimestre 2026',
            ],
            [
                'title'           => 'Entrega de Calificaciones II Cuatrimestre',
                'description'     => 'Fecha límite para la entrega de calificaciones del II Cuatrimestre 2026.',
                'start_date'      => '2026-10-05 00:00:00',
                'end_date'        => '2026-10-09 23:59:59',
                'category'        => 'grades',
                'target_scope'    => 'all',
                'academic_period' => 'II Cuatrimestre 2026',
            ],
            [
                'title'           => 'Feria de Emprendimiento e Innovación UNCPGGL',
                'description'     => 'Actividad universitaria de ejemplo: exposición de proyectos de emprendimiento e innovación de los estudiantes de todas las carreras.',
                'start_date'      => '2026-11-20 09:00:00',
                'end_date'        => '2026-11-20 17:00:00',
                'category'        => 'activity',
                'target_scope'    => 'all',
                'campus'          => 'Campus Central',
                'location'        => 'Aula Magna y explanada central',
                'organizer'       => 'Dirección de Extensión Universitaria',
            ],
            [
                'title'           => 'Vacaciones Estudiantiles de Fin de Año',
                'description'     => 'Receso académico de fin de año.',
                'start_date'      => '2026-12-14 00:00:00',
                'end_date'        => '2026-12-31 23:59:59',
                'category'        => 'vacation',
                'target_scope'    => 'all',
                'campus'          => 'Campus Central',
            ],
        ];

        foreach ($events as $event) {
            UniversityEvent::firstOrCreate(
                ['title' => $event['title']],
                $event + ['is_active' => true]
            );
        }
    }
}
