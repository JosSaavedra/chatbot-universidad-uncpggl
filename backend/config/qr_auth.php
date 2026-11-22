<?php

/**
 * Configuración privada del acceso QR + Google OIDC.
 *
 * Los correos y secretos se leen sólo desde variables de entorno privadas.
 * React nunca recibe esta información.
 */
$accounts = [];

// Exigir QR + Google aunque GOOGLE_ACCOUNT_LINKS falte o esté incompleta.
$protectedCarnets = [
    '2024-med-5837',
    '2023-isi-9264',
    '2024-adm-1748',
    '2022-ici-8392',
    '2025-enf-4621',
    '2023-med-7056',

    // Reservar los identificadores anteriores ante una restauración de SQLite.
    '2024-med-001',
    '2023-isi-001',
    '2024-adm-001',
    '2022-ici-001',
    '2025-enf-001',
    '2023-med-002',
];

$raw = env('GOOGLE_ACCOUNT_LINKS');
$present = $raw !== null && (!is_string($raw) || trim($raw) !== '');
$valid = false;

if ($present && is_string($raw)) {
    $decoded = json_decode($raw, true);

    $valid = is_array(json_decode($raw)) && is_array($decoded);
    if ($valid) {
        foreach ($decoded as $entry) {
            if (!is_array($entry) || !is_string($entry['email'] ?? null)
                || !is_string($entry['carnets'] ?? null) || !is_string($entry['owner'] ?? '')) {
                $valid = false;
                break;
            }
            $email = trim((string) ($entry['email'] ?? ''));
            $carnets = [];

            foreach (explode(',', (string) ($entry['carnets'] ?? '')) as $carnet) {
                $code = strtolower(trim($carnet));
                if ($code !== '') $carnets[] = $code;
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL) && $carnets !== []) {
                $accounts[] = [
                    'owner' => trim((string) ($entry['owner'] ?? '')),
                    'email' => strtolower($email),
                    'carnets' => array_values(array_unique($carnets)),
                ];
            } else {
                $valid = false;
            }
        }
    }
}

// Las seis vinculaciones autorizadas viven en GOOGLE_ACCOUNT_LINKS. Sus
// carnés se suman a la línea base para que la protección no dependa de una
// variable ausente, pero tampoco se pierda ningún carné configurado.
if ($valid) {
    foreach ($accounts as $account) {
        foreach ($account['carnets'] as $carnet) {
            $protectedCarnets[] = strtolower($carnet);
        }
    }
}

return [
    'email_accounts' => [
        '2024-med-5837' => env('QR_EMAIL_2024_MED_5837'),
        '2024-adm-1748' => env('QR_EMAIL_2024_ADM_1748'),
        '2025-enf-4621' => env('QR_EMAIL_2025_ENF_4621'),
    ],
    'protected_carnets' => array_values(array_unique($protectedCarnets)),
    'accounts' => $accounts,
    'accounts_present' => $present,
    'accounts_valid' => $valid,
    'ttl_minutes' => max(1, (int) env('QR_AUTH_TTL_MINUTES', 10)),
    'base_url' => rtrim(trim((string) env('QR_AUTH_URL')) ?: trim((string) env('APP_URL', 'http://localhost:8000')), '/'),
    'jwks_cache_seconds' => max(60, (int) env('GOOGLE_JWKS_CACHE_SECONDS', 3600)),

    // Sólo para el E2E local: nunca definir en producción.
    'test_oidc_verifier' => env('QR_AUTH_TEST_OIDC_VERIFIER', ''),
];
