<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Memoria conversacional
    |--------------------------------------------------------------------------
    |
    | Número de intercambios (mensaje del usuario + respuesta del bot) que se
    | usan localmente y pueden enviarse desde el chat invitado para seguimientos del
    | tipo "¿y cuánto cuesta?" o "¿quién es mi profesor de esa materia?".
    |
    | La memoria SIEMPRE se limita a la conversación actual: nunca se mezclan
    | mensajes de otros estudiantes ni de otras sesiones.
    |
    */
    'memory' => [
        'exchanges' => (int) env('CHATBOT_MEMORY_EXCHANGES', 6),
    ],

    'privacy' => [

        // Aviso mostrado en el prototipo (frontend y documentación).
        'notice' => 'Prototipo académico de demostración. La información de carreras, '
            . 'horarios, admisión y calendario puede ser simulada. Tus mensajes se '
            . 'procesan con un servicio de IA (Groq) para redactar la respuesta. '
            . 'No ingreses datos sensibles (contraseñas, datos bancarios, etc.).',
    ],
];
