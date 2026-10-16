<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Horario de atención al público
    |--------------------------------------------------------------------------
    |
    | Días y horas en que la universidad atiende al público. El chatbot usa
    | esta información para responder cuando el usuario pregunta por el
    | horario de atención (por ejemplo, desde el botón "Horario de atención").
    |
    */
    'office_hours' => [
        ['days' => 'Lunes a Sábado', 'open' => '8:00 a.m.', 'close' => '4:00 p.m.'],
    ],

    'office_hours_note' => 'Horario de atención al público de la universidad.',
];
