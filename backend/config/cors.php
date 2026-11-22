<?php

$local = ! env('RENDER', false) && env('APP_ENV', 'production') === 'local';
$origins = array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')));
$origins = array_filter($origins, fn (string $origin): bool => preg_match(
    $local ? '#^https?://[a-z0-9.-]+(?::[0-9]+)?$#i' : '#^https://[a-z0-9.-]+(?::[0-9]+)?$#i',
    $origin,
) === 1);

// En desarrollo Vite puede arrancar en cualquier puerto (5174, 5178...),
// así que se aceptan todos los orígenes locales sin fijar un puerto.
$localOriginPatterns = $local
    ? ['#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#']
    : [];
$originPatterns = array_map(fn (string $origin): string => '#^'.preg_quote($origin, '#').'$#D', $origins);

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => [],
    'allowed_origins_patterns' => [...$originPatterns, ...$localOriginPatterns],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Approval-Nonce', 'X-Chat-Guest-Token'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
