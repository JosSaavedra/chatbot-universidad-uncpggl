<?php

namespace Tests\Support;

use App\Services\GoogleOidcVerifier;
use RuntimeException;

/** Simula Google sin red ni credenciales reales. */
class FakeGoogleOidcVerifier extends GoogleOidcVerifier
{
    private const IDENTITIES = [
        'google-jose' => ['sub' => 'sub-jose', 'email' => 'owner-one@example.com'],
        'google-jose-clon' => ['sub' => 'sub-jose-nuevo', 'email' => 'owner-one@example.com'],
        'google-cesar' => ['sub' => 'sub-cesar', 'email' => 'owner-two@example.com'],
        'google-rodiver' => ['sub' => 'sub-rodiver', 'email' => 'owner-three@example.com'],
        'google-attacker' => ['sub' => 'sub-attacker', 'email' => 'attacker@example.com'],
    ];

    public function verify(string $idToken, string $expectedNonce): array
    {
        if ($expectedNonce === '') {
            throw new RuntimeException('Falta el nonce de la solicitud.');
        }
        if ($idToken === 'google-bad-nonce' || ($idToken === 'google-jose' && $expectedNonce === 'nonce-malo')) {
            throw new RuntimeException('El nonce no coincide.');
        }
        if ($idToken === 'google-falso') {
            throw new RuntimeException('ID Token falsificado.');
        }
        if (!isset(self::IDENTITIES[$idToken])) {
            throw new RuntimeException('ID Token desconocido.');
        }

        return self::IDENTITIES[$idToken] + ['email_verified' => true];
    }
}
