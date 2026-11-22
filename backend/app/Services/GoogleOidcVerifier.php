<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Verifica el ID Token de Google usando el JWKS público.
 * Sólo se usa en el servidor; ningún claim se toma de React.
 */
class GoogleOidcVerifier
{
    /** @return array{sub:string,email:string,email_verified:bool} */
    public function verify(string $idToken, string $expectedNonce): array
    {
        $keys = Cache::remember(
            'google_oidc_jwks',
            (int) config('qr_auth.jwks_cache_seconds', 3600),
            function () {
                $response = Http::get(config('services.google.jwks_url'));

                if ($response->failed() || !is_array($response->json())) {
                    throw new RuntimeException('No se pudo obtener la configuración de claves de Google.');
                }

                return $response->json();
            }
        );

        try {
            // php-jwt v7 usa el `alg` de cada Key devuelta por parseKeySet.
            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($keys, 'RS256'));
        } catch (Throwable) {
            throw new RuntimeException('El ID Token de Google no es válido.');
        }

        // `exp` e `iat` son obligatorios: php-jwt sólo comprueba `exp` cuando
        // el claim existe, así que un token sin ellos debe rechazarse aquí.
        $now = time();
        $issuedAt = $claims['iat'] ?? null;
        $expiresAt = $claims['exp'] ?? null;
        if (! is_int($issuedAt) || ! is_int($expiresAt)
            || $issuedAt > $now + 60
            || $expiresAt <= $now
            || $expiresAt <= $issuedAt) {
            throw new RuntimeException('El ID Token de Google no es válido.');
        }

        $issuer = $claims['iss'] ?? null;
        if (!in_array($issuer, ['https://accounts.google.com', 'accounts.google.com'], true)) {
            throw new RuntimeException('El emisor del ID Token no es Google.');
        }

        $clientId = (string) config('services.google.client_id');
        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        if (!in_array($clientId, $audiences, true)) {
            throw new RuntimeException('El ID Token no fue emitido para esta aplicación.');
        }

        if (isset($claims['azp']) && $claims['azp'] !== $clientId) {
            throw new RuntimeException('El ID Token tiene un cliente autorizado distinto.');
        }

        if (empty($claims['nonce']) || !hash_equals($expectedNonce, (string) $claims['nonce'])) {
            throw new RuntimeException('El ID Token no corresponde a esta solicitud.');
        }

        if (($claims['email_verified'] ?? false) !== true) {
            throw new RuntimeException('La cuenta de Google no tiene el correo verificado.');
        }

        $sub = (string) ($claims['sub'] ?? '');
        $email = strtolower((string) ($claims['email'] ?? ''));

        if ($sub === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('La identidad de Google está incompleta.');
        }

        return ['sub' => $sub, 'email' => $email, 'email_verified' => true];
    }
}
