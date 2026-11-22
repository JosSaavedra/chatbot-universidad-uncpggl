<?php

return [
    // Activar solamente en una instalación aislada con datos ficticios.
    'demo' => env('STUDENT_ACCESS_MODE', 'institutional') === 'demo',
    'idle_minutes' => max(1, (int) env('TOKEN_IDLE_MINUTES', 30)),
    'demo_carnets' => [
        '2024-med-001', '2023-isi-001', '2024-adm-001', '2022-ici-001',
        '2025-enf-001', '2023-med-002', '2024-con-001', '2025-isi-002',
        '2022-arq-001', '2023-agr-001',
    ],
];
