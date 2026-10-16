<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Información de admisión
    |--------------------------------------------------------------------------
    |
    | Requisitos y documentos generales para inscribirse en la UNCPGGL. El
    | chatbot usa esta información cuando el estudiante pregunta por los
    | requisitos o documentos necesarios para inscribirse, y la muestra en una
    | tarjeta dentro del chat (datos estructurados, no generados por la IA).
    |
    */

    'title' => 'Requisitos de admisión',

    'intro' => 'Para inscribirte en la UNCPGGL necesitas cumplir con lo siguiente:',

    'sections' => [
        [
            'heading' => 'Documentos',
            'items' => [
                'Título de bachillerato (original y copia).',
                'Notas de bachillerato certificadas.',
                'Partida de nacimiento.',
                'Cédula de identidad (original y copia).',
                'Dos fotografías tamaño carné.',
                'Certificado médico o de salud.',
            ],
        ],
        [
            'heading' => 'Requisitos académicos',
            'items' => [
                'Aprobar el examen de admisión.',
                'Aprobar el curso propedéutico de nivelación.',
            ],
        ],
        [
            'heading' => 'Pasos del proceso',
            'items' => [
                'Llenar el formulario de solicitud de ingreso.',
                'Entregar los documentos en el departamento de Admisión.',
                'Realizar el pago de inscripción.',
                'Matricular las asignaturas del primer período.',
            ],
        ],
    ],

    'note' => 'Algunas carreras pueden solicitar requisitos adicionales (por ejemplo, certificado de salud o aptitud física). Consulta en el departamento de Admisión.',
];
