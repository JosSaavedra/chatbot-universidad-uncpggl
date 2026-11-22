<?php

namespace Tests;

use App\Services\GoogleOidcVerifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\FakeGoogleOidcVerifier;

abstract class TestCase extends BaseTestCase
{
    protected function guestChatHeaders(string $sessionId): array
    {
        return ['X-Chat-Guest-Token' => Crypt::encryptString(json_encode(['session' => $sessionId, 'expires' => now()->addDay()->timestamp], JSON_THROW_ON_ERROR))];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Correos ficticios: los reales viven sólo en el .env privado del backend.
        config([
            'qr_auth.accounts_present' => true,
            'qr_auth.accounts_valid' => true,
            'qr_auth.protected_carnets' => [
                '2024-med-5837',
                '2023-isi-9264',
                '2024-adm-1748',
                '2022-ici-8392',
                '2025-enf-4621',
                '2023-med-7056',
            ],
            'qr_auth.accounts' => [
                [
                    'owner' => 'jose',
                    'email' => 'owner-one@example.com',
                    'carnets' => ['2024-med-5837', '2023-isi-9264'],
                ],
                [
                    'owner' => 'cesar',
                    'email' => 'owner-two@example.com',
                    'carnets' => ['2024-adm-1748', '2022-ici-8392'],
                ],
                [
                    'owner' => 'rodiver',
                    'email' => 'owner-three@example.com',
                    'carnets' => ['2025-enf-4621', '2023-med-7056'],
                ],
            ],
            'qr_auth.base_url' => 'http://localhost:8000',
            'services.google.client_id' => 'cliente-prueba.apps.googleusercontent.com',
            'services.google.client_secret' => 'secreto-de-prueba',
            'services.google.redirect' => 'http://localhost:8000/auth/google/callback',
        ]);

        $this->app->instance(GoogleOidcVerifier::class, new FakeGoogleOidcVerifier());
    }
}
