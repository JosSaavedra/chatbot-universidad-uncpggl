<?php

return [
    // Interruptor explícito para Render Free: si la base SQLite está vacía,
    // `php artisan demo:ensure-seeded` crea los datos ficticios una sola vez.
    // Si ya existe cualquier registro sembrado, el comando no escribe nada y
    // nunca restablece contraseñas ni sobrescribe cuentas.
    'seed_on_empty' => filter_var(env('DEMO_SEED_ON_EMPTY', false), FILTER_VALIDATE_BOOL),
];
