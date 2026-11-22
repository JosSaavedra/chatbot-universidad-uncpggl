<?php

return [
    // Activar solamente en una instalación aislada con datos ficticios.
    'demo' => env('STUDENT_ACCESS_MODE', 'institutional') === 'demo',
    'idle_minutes' => max(1, (int) env('TOKEN_IDLE_MINUTES', 30)),
    'demo_carnets' => [
        '2024-med-5837', '2023-isi-9264', '2024-adm-1748', '2022-ici-8392',
        '2025-enf-4621', '2023-med-7056', '2024-con-3189', '2025-isi-6473',
        '2022-arq-2815', '2023-agr-9542',
    ],
];
