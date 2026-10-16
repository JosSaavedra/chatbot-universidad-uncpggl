<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Memoria conversacional
    |--------------------------------------------------------------------------
    |
    | Número de intercambios (mensaje del usuario + respuesta del bot) que se
    | envían como contexto a la IA para comprender preguntas de seguimiento del
    | tipo "¿y cuánto cuesta?" o "¿quién es mi profesor de esa materia?".
    |
    | La memoria SIEMPRE se limita a la conversación actual: nunca se mezclan
    | mensajes de otros estudiantes ni de otras sesiones.
    |
    */
    'memory' => [
        'exchanges' => (int) env('CHATBOT_MEMORY_EXCHANGES', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacidad de los datos enviados a la IA
    |--------------------------------------------------------------------------
    |
    | El asistente resuelve la información académica con consultas estructuradas
    | a la base de datos (Laravel/SQLite) y tarjetas deterministas. La IA solo
    | redacta. Por eso se minimiza lo que se transmite al proveedor del modelo
    | (Groq):
    |
    |  - send_identity:       NUNCA se envían nombre ni carné (no son
    |                         indispensables para responder). Desactivado por
    |                         defecto.
    |  - send_personal_data:  datos académicos privados (notas, exámenes, becas,
    |                         tutorías). Aun activado, sólo se incluyen cuando la
    |                         consulta lo pide explícitamente (filtrado por
    |                         intención), nunca en cada mensaje.
    |
    */
    'privacy' => [
        'send_identity'      => (bool) env('CHATBOT_SEND_IDENTITY', false),
        'send_personal_data' => (bool) env('CHATBOT_SEND_PERSONAL_DATA', true),

        // Aviso mostrado en el prototipo (frontend y documentación).
        'notice' => 'Prototipo académico de demostración. La información de carreras, '
            . 'horarios, admisión y calendario puede ser simulada. Tus mensajes se '
            . 'procesan con un servicio de IA (Groq) para redactar la respuesta. '
            . 'No ingreses datos sensibles (contraseñas, datos bancarios, etc.).',
    ],
];
