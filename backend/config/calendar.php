<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Categorías del calendario académico
    |--------------------------------------------------------------------------
    |
    | Cada categoría tiene un color asociado que el frontend usa para pintar
    | los eventos. Los feriados nacionales se obtienen automáticamente desde
    | Nager.Date y usan la categoría "holiday".
    |
    | NO se inventan fechas oficiales: el administrador registra los eventos
    | reales de la universidad desde el panel de administración.
    |
    */
    'categories' => [
        'holiday'     => ['label' => 'Feriado nacional',              'color' => '#dc2626'], // Rojo
        'inscription' => ['label' => 'Matrículas',                    'color' => '#2563eb'], // Azul
        'exam'        => ['label' => 'Exámenes',                      'color' => '#ea580c'], // Naranja
        'term'        => ['label' => 'Inicio / fin de cuatrimestre',  'color' => '#16a34a'], // Verde
        'activity'    => ['label' => 'Actividades universitarias',    'color' => '#7c3aed'], // Morado
        'vacation'    => ['label' => 'Vacaciones académicas',         'color' => '#0d9488'], // Teal
        'grades'      => ['label' => 'Entrega de calificaciones',     'color' => '#db2777'], // Rosa
        'institutional' => ['label' => 'Reuniones institucionales',   'color' => '#64748b'], // Gris azulado
        'other'       => ['label' => 'Otros eventos',                 'color' => '#0891b2'], // Cian
    ],

    /*
    |--------------------------------------------------------------------------
    | Zona horaria
    |--------------------------------------------------------------------------
    */
    'timezone' => 'America/Managua',

    /*
    |--------------------------------------------------------------------------
    | Fuente de feriados (Nager.Date)
    |--------------------------------------------------------------------------
    */
    'holidays_api' => 'https://date.nager.at/api/v3/PublicHolidays/{year}/NI',
];
