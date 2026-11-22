<?php

namespace Tests\Feature;

use App\Services\GoogleOidcVerifier;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class GoogleOidcVerifierTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey;

    private string $otherPrivateKey;

    private string $opensslConfig;

    private array $jwks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->opensslConfig = sys_get_temp_dir().DIRECTORY_SEPARATOR.'qr-openssl-'.getmypid().'.cnf';
        file_put_contents($this->opensslConfig, "[req]\ndistinguished_name=req_distinguished_name\n[req_distinguished_name]\n[v3_ca]\n");
        $this->privateKey = $this->newRsaKey($details);
        $this->otherPrivateKey = $this->newRsaKey();
        $this->jwks = ['keys' => [[
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => 'clave-prueba',
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ]]];

        // Esta prueba necesita el verificador real, no el doble del flujo QR.
        $this->app->instance(GoogleOidcVerifier::class, new GoogleOidcVerifier);
        Cache::forget('google_oidc_jwks');
        Http::fake([
            config('services.google.jwks_url') => Http::response($this->jwks, 200),
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->opensslConfig) && is_file($this->opensslConfig)) {
            unlink($this->opensslConfig);
        }
        parent::tearDown();
    }

    private function newRsaKey(?array &$details = null): string
    {
        $options = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $this->opensslConfig,
        ];
        $resource = openssl_pkey_new($options);
        openssl_pkey_export($resource, $pem, null, ['config' => $this->opensslConfig]);
        if (func_num_args() > 0) {
            $details = openssl_pkey_get_details($resource);
        }

        return $pem;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function claims(array $overrides = []): array
    {
        return array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => config('services.google.client_id'),
            'sub' => 'sub-real-de-google',
            'email' => 'OWNER-ONE@EXAMPLE.COM',
            'email_verified' => true,
            'nonce' => 'nonce-generado-por-laravel',
            'iat' => time(),
            'exp' => time() + 300,
        ], $overrides);
    }

    private function token(array $overrides = [], ?string $key = null): string
    {
        return JWT::encode($this->claims($overrides), $key ?? $this->privateKey, 'RS256', 'clave-prueba');
    }

    public function test_accepts_a_well_formed_id_token_and_normalizes_the_email(): void
    {
        $claims = $this->app->make(GoogleOidcVerifier::class)
            ->verify($this->token(), 'nonce-generado-por-laravel');

        $this->assertSame('sub-real-de-google', $claims['sub']);
        $this->assertSame('owner-one@example.com', $claims['email']);
        $this->assertTrue($claims['email_verified']);
    }

    public function test_accepts_an_audience_array_and_accounts_google_issuer_alias(): void
    {
        $claims = $this->app->make(GoogleOidcVerifier::class)->verify($this->token([
            'iss' => 'accounts.google.com',
            'aud' => ['otra-app.apps.googleusercontent.com', config('services.google.client_id')],
            'azp' => config('services.google.client_id'),
        ]), 'nonce-generado-por-laravel');

        $this->assertSame('sub-real-de-google', $claims['sub']);
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function invalidTokenCases(): array
    {
        return [
            'firma falsificada' => ['firma', 'signature', 'El ID Token de Google no es válido.'],
            'emisor distinto' => ['issuer', 'iss', 'El emisor del ID Token no es Google.'],
            'audiencia ajena' => ['audience', 'aud', 'El ID Token no fue emitido para esta aplicación.'],
            'cliente autorizado distinto' => ['azp', 'azp', 'El ID Token tiene un cliente autorizado distinto.'],
            'nonce ajeno' => ['nonce', 'nonce', 'El ID Token no corresponde a esta solicitud.'],
            'correo sin verificar' => ['verified', 'email_verified', 'La cuenta de Google no tiene el correo verificado.'],
            'expirado' => ['expired', 'exp', 'El ID Token de Google no es válido.'],
            'sin sub' => ['subject', 'sub', 'La identidad de Google está incompleta.'],
        ];
    }

    #[DataProvider('invalidTokenCases')]
    public function test_rejects_invalid_id_tokens(string $case, string $field, string $expectedMessage): void
    {
        $overrides = match ($field) {
            'iss' => ['iss' => 'https://atacante.example'],
            'aud' => ['aud' => 'otra-app.apps.googleusercontent.com'],
            'azp' => ['azp' => 'otra-app.apps.googleusercontent.com'],
            'nonce' => ['nonce' => 'nonce-de-otra-solicitud'],
            'email_verified' => ['email_verified' => false],
            'exp' => ['exp' => time() - 10],
            'sub' => ['sub' => ''],
            default => [],
        };

        $key = $field === 'signature' ? $this->otherPrivateKey : null;
        $token = $field === 'signature' ? $this->token([], $key) : $this->token($overrides);

        try {
            $this->app->make(GoogleOidcVerifier::class)->verify($token, 'nonce-generado-por-laravel');
            $this->fail('El caso '.$case.' debió ser rechazado.');
        } catch (RuntimeException $error) {
            $this->assertSame($expectedMessage, $error->getMessage());
        }
    }
}
