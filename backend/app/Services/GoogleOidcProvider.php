<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User;
use RuntimeException;

/**
 * Proveedor Socialite para Google OIDC con PKCE y validación del ID Token.
 * No usa stateless(): Socialite comprueba `state` y `code_verifier` en sesión.
 */
class GoogleOidcProvider extends GoogleProvider
{
    protected $scopes = ['openid', 'email', 'profile'];

    private GoogleOidcVerifier $oidcVerifier;

    /** @var array{sub:string,email:string,email_verified:bool} */
    private array $verifiedClaims = [];

    public function __construct(
        Request $request,
        $clientId,
        $clientSecret,
        $redirectUrl,
        GoogleOidcVerifier $oidcVerifier,
        $guzzle = []
    ) {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle);
        $this->oidcVerifier = $oidcVerifier;
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase((string) config('services.google.auth_url'), $state);
    }

    protected function getTokenUrl(): string
    {
        return (string) config('services.google.token_url');
    }

    /** Añade nonce y prompt sólo a la URL de autorización, nunca al token endpoint. */
    protected function getCodeFields($state = null): array
    {
        $fields = parent::getCodeFields($state);
        $nonce = $this->request->session()->get('qr_google_nonce');

        if (is_string($nonce) && $nonce !== '') {
            $fields['nonce'] = $nonce;
        }
        $fields['prompt'] = 'select_account';

        return $fields;
    }

    /** Intercambia el código usando Http::fake() en pruebas y PKCE en producción. */
    public function getAccessTokenResponse($code)
    {
        $response = Http::asForm()->post($this->getTokenUrl(), $this->getTokenFields($code));

        if ($response->failed() || !is_array($response->json())) {
            throw new RuntimeException('Google no pudo completar la autorización.');
        }

        return $response->json();
    }

    public function user(): User
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }

        $response = $this->getAccessTokenResponse($this->getCode());
        $idToken = Arr::get($response, 'id_token');

        if (!is_string($idToken) || $idToken === '') {
            throw new RuntimeException('Google no devolvió un ID Token.');
        }

        $expectedNonce = (string) $this->request->session()->get('qr_google_nonce', '');
        $this->verifiedClaims = $this->oidcVerifier->verify($idToken, $expectedNonce);
        $this->request->session()->forget('qr_google_nonce');

        // GoogleProvider::mapUserToObject devuelve un User; userInstance espera el array de claims.
        return $this->user = $this->userInstance($response, $this->verifiedClaims);
    }

    /** @return array{sub:string,email:string,email_verified:bool} */
    public function verifiedClaims(): array
    {
        return $this->verifiedClaims;
    }
}
