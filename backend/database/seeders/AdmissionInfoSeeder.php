<?php

namespace Database\Seeders;

use App\Models\AdmissionItem;
use Illuminate\Database\Seeder;

/**
 * Información de admisión de ejemplo para el prototipo.
 *
 * Son datos SIMULADOS (is_demo = true). No sustituyen la información oficial
 * de la universidad y el chatbot los identifica como "Datos de demostración".
 * Idempotente: si ya hay registros, no vuelve a insertar.
 */
class AdmissionInfoSeeder extends Seeder
{
    public function run(): void
    {
        if (AdmissionItem::query()->exists()) {
            return;
        }

        $sections = [
            'requisitos' => [
                'Título de bachillerato en cualquiera de sus modalidades (original y copia).',
                'Aprobar el examen de admisión de la UNCPGGL.',
                'Aprobar el curso propedéutico de nivelación.',
                'Cumplir con la nota mínima de ingreso establecida por la facultad.',
            ],
            'documentos' => [
                'Partida de nacimiento (original y copia).',
                'Cédula de identidad (original y copia).',
                'Notas de bachillerato certificadas.',
                'Dos fotografías tamaño carné.',
                'Certificado médico o de salud.',
                'Carné de vacunación actualizado (según la carrera).',
            ],
            'pasos' => [
                'Llenar el formulario de solicitud de ingreso en línea o en el departamento de Admisión.',
                'Entregar los documentos en el departamento de Admisión.',
                'Realizar el pago de inscripción.',
                'Matricular las asignaturas del primer período académico.',
            ],
        ];

        $sort = 0;
        $rows = [];
        foreach ($sections as $section => $items) {
            foreach ($items as $item) {
                $rows[] = [
                    'section'    => $section,
                    'content'    => $item,
                    'sort_order' => $sort++,
                    'is_demo'    => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        AdmissionItem::query()->insert($rows);
    }
}
