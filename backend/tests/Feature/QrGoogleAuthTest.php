<?php

namespace Tests\Feature;

use App\Models\GooglePreauthorization;
use App\Models\QrAuthRequest;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QrGoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $currentGoogleToken = 'google-jose';

    private const IDENTITIES = [
        '2024-med-001' => 'google-jose',
        '2023-isi-001' => 'google-jose',
        '2024-adm-001' => 'google-cesar',
        '2022-ici-001' => 'google-cesar',
        '2025-enf-001' => 'google-rodiver',
        '2023-med-002' => 'google-rodiver',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(0, Artisan::call('qr-google:sync-links'));
        $this->assertSame(3, GooglePreauthorization::count());

        // Un único stub mutable: Http::fake() acumula stubs y el primero ganaría.
        Http::fake([
            config('services.google.token_url') => fn () => Http::response([
                'access_token' => 'access-de-prueba',
                'id_token' => $this->currentGoogleToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
        ]);
    }

    private function qrRow(array $data): QrAuthRequest
    {
        return QrAuthRequest::where('public_id', $data['request_id'])->firstOrFail();
    }

    private function requestQr(string $carnet): array
    {
        $response = $this->postJson('/api/qr-auth/request', ['carnet' => $carnet])->assertStatus(202);
        $data = $response->json();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $data['request_id']);
        $this->assertSame(64, strlen($data['client_nonce']));
        $this->assertStringStartsWith(config('qr_auth.base_url').'/acceso/qr/', $data['qr_url']);
        $this->assertSame(6, strlen((string) $data['confirm_code']));
        $this->assertMatchesRegularExpression('/^\d{6}$/D', (string) $data['confirm_code']);

        $token = basename((string) parse_url($data['qr_url'], PHP_URL_PATH));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $token);
        $row = $this->qrRow($data);
        $this->assertSame(hash('sha256', $token), $row->qr_token_hash);
        $this->assertSame(hash('sha256', $data['client_nonce']), $row->client_hash);
        $this->assertSame(strtolower($carnet), strtolower($row->carnet));

        return $data;
    }

    private function scan(array $data)
    {
        $path = (string) parse_url($data['qr_url'], PHP_URL_PATH);
        $row = $this->qrRow($data);

        return $this->get($path)
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertViewMissing("confirmCode")
            ->assertSee($row->carnet);
    }

    private function startGoogle(array $query = []): array
    {
        $response = $this->get(route('qr.google.start'))->assertRedirect();
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $fields);

        $this->assertSame('https', parse_url($location, PHP_URL_SCHEME));
        $this->assertSame('accounts.google.com', parse_url($location, PHP_URL_HOST));
        $this->assertSame('code', $fields['response_type'] ?? null);
        $this->assertSame('openid email profile', $fields['scope'] ?? null);
        $this->assertSame('S256', $fields['code_challenge_method'] ?? null);
        $this->assertNotEmpty($fields['state'] ?? '');
        $this->assertNotEmpty($fields['code_challenge'] ?? '');
        $this->assertSame(session('qr_google_nonce'), $fields['nonce'] ?? null);
        $this->assertSame(session('state'), $fields['state'] ?? null);
        $this->assertArrayNotHasKey('access_token', $fields);
        $this->assertArrayNotHasKey('client_secret', $fields);

        return array_merge($fields, $query);
    }

    private function googleCallback(array $fields, string $idToken = 'google-jose', array $overrides = [])
    {
        $this->currentGoogleToken = $idToken;

        return $this->get(route('qr.google.callback', array_merge([
            'code' => 'code-'.bin2hex(random_bytes(8)),
            'state' => $fields['state'],
        ], $overrides)));
    }

    private function approve(array $data, bool $expectSuccess = true)
    {
        $row = $this->qrRow($data);
        $response = $this->post(route('qr.approve'), ['confirm_code' => $row->confirm_code]);

        return $expectSuccess
            ? $response->assertOk()->assertSee('Acceso autorizado')
            : $response;
    }

    private function qrStatus(array $data, ?string $nonce = null)
    {
        return $this->withHeader('X-Approval-Nonce', $nonce ?? $data['client_nonce'])
            ->getJson('/api/qr-auth/status/'.$data['request_id'])
            ->assertOk();
    }

    private function exchange(array $data, ?string $nonce = null, int $status = 200)
    {
        return $this->withHeader('X-Approval-Nonce', $nonce ?? $data['client_nonce'])
            ->postJson('/api/qr-auth/exchange', ['request_id' => $data['request_id']])
            ->assertStatus($status);
    }

    private function cancel(array $data, ?string $nonce = null)
    {
        return $this->withHeader('X-Approval-Nonce', $nonce ?? $data['client_nonce'])
            ->postJson('/api/qr-auth/cancel', ['request_id' => $data['request_id']]);
    }

    private function authorize(string $carnet, string $idToken = 'google-jose'): array
    {
        $data = $this->requestQr($carnet);
        $this->scan($data);
        $this->googleCallback($this->startGoogle(), $idToken)->assertRedirect(route('qr.review'));

        return $data;
    }

    private function completeLogin(string $carnet, ?string $idToken = null): array
    {
        $data = $this->authorize($carnet, $idToken ?? self::IDENTITIES[strtolower($carnet)]);
        $this->approve($data);
        $this->qrStatus($data)->assertJsonPath('status', 'approved');

        return $this->exchange($data)->json();
    }

    public function test_qr_url_uses_the_configured_backend_origin_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['qr_auth.base_url' => 'https://backend.onrender.com/']);
        $url = $this->withHeaders(['X-Forwarded-Host' => 'other.example'])
            ->postJson('/api/qr-auth/request', ['carnet' => '2024-MED-001'])
            ->assertAccepted()->json('qr_url');

        $this->assertMatchesRegularExpression('#^https://backend\.onrender\.com/acceso/qr/[a-f0-9]{64}$#D', $url);
    }

    public function test_qr_url_refuses_relative_origins_and_plain_http_in_production(): void
    {
        foreach (['', '/acceso', '//other.example'] as $origin) {
            config(['qr_auth.base_url' => $origin]);
            $this->postJson('/api/qr-auth/request', ['carnet' => '2024-MED-001'])->assertServiceUnavailable();
        }

        $this->app['env'] = 'production';
        config(['qr_auth.base_url' => 'http://backend.onrender.com']);
        $this->postJson('/api/qr-auth/request', ['carnet' => '2024-MED-001'])->assertServiceUnavailable();
        $this->assertDatabaseCount('qr_auth_requests', 0);
    }

    public function test_request_only_contains_a_temporary_qr_and_no_personal_data(): void
    {
        $this->seed();
        $data = $this->requestQr('2024-MED-001');

        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('carnet', $data);
        $this->assertArrayNotHasKey('google_sub', $data);
        $this->assertStringNotContainsString('@', json_encode($data));
        $this->assertStringNotContainsString('id_token', json_encode($data));
        $this->assertStringNotContainsString('code_verifier', json_encode($data));
        $this->assertStringNotContainsString('qr-google', json_encode($data));

        $this->getJson('/api/auth/mode')
            ->assertOk()
            ->assertJsonPath('mode', 'institutional')
            ->assertJsonMissingPath('qr_required')
            ->assertJsonMissingPath('approval_required');

        $this->qrStatus($data)->assertExactJson([
            'status' => 'pending',
            'expires_at' => $this->qrRow($data)->expires_at->toISOString(),
        ]);
    }

    public function test_google_flow_binds_the_sub_and_issues_one_revocable_session(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed();
        $student = Student::whereRaw('LOWER(student_code) = ?', ['2024-med-001'])->firstOrFail();

        $data = $this->requestQr($student->student_code);
        $this->scan($data);
        $fields = $this->startGoogle();
        $codeVerifier = session('code_verifier');
        $this->assertIsString($codeVerifier);

        $this->googleCallback($fields, 'google-jose')->assertRedirect(route('qr.review'));
        Http::assertSent(function ($request) use ($codeVerifier) {
            return $request->url() === config('services.google.token_url')
                && $request['code_verifier'] === $codeVerifier
                && $request['grant_type'] === 'authorization_code';
        });

        $this->assertDatabaseHas('qr_auth_requests', [
            'public_id' => $data['request_id'],
            'status' => 'google_authenticated',
            'google_sub' => 'sub-jose',
            'google_email_hash' => hash('sha256', 'owner-one@example.com'),
        ]);
        $this->assertDatabaseHas('google_preauthorizations', [
            'google_sub' => 'sub-jose',
            'owner_label' => 'jose',
        ]);
        $this->get(route('qr.review'))->assertOk()->assertViewMissing('confirmCode');

        $this->approve($data);
        $this->qrStatus($data)->assertJsonPath('status', 'approved');
        $session = $this->exchange($data)->json();

        $this->assertSame('qr-google', DB::table('personal_access_tokens')
            ->where('tokenable_id', $student->user_id)->value('name'));
        $this->assertSame($student->student_code, $session['student']['carnet']);
        $this->assertSame(config('student_access.idle_minutes'), $session['idle_minutes']);

        Auth::forgetGuards();
        $this->withToken($session['token'])->getJson('/api/my-grades')
            ->assertOk()
            ->assertJsonPath('data.student.carnet', $student->student_code);

        $this->exchange($data, null, 409);
        $this->get((string) parse_url($data['qr_url'], PHP_URL_PATH))
            ->assertOk()
            ->assertSee('Solicitud ya utilizada');
    }

    public function test_all_six_protected_accounts_see_only_their_own_records(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed();

        $tokens = [];
        $receipts = [];
        foreach (config('qr_auth.protected_carnets') as $code) {
            $student = Student::whereRaw('LOWER(student_code) = ?', [$code])->firstOrFail();
            $session = $this->completeLogin($code);
            $tokens[$code] = $session['token'];

            Auth::forgetGuards();
            $payments = $this->withToken($session['token'])->getJson('/api/student-payments')
                ->assertOk()
                ->assertJsonPath('data.student.carnet', $student->student_code)
                ->json('data.payments');
            $receipts[$code] = array_column($payments, 'id');
            $this->assertNotEmpty($receipts[$code]);
        }

        foreach ($tokens as $code => $token) {
            $own = Student::whereRaw('LOWER(student_code) = ?', [$code])->firstOrFail();
            foreach ($receipts as $otherCode => $ids) {
                if ($otherCode === $code) {
                    continue;
                }
                foreach ($ids as $id) {
                    Auth::forgetGuards();
                    $this->withToken($token)->getJson('/api/student-payments/'.$id.'/receipt')->assertNotFound();
                }
            }
            foreach (config('qr_auth.protected_carnets') as $otherCode) {
                if ($otherCode === $code) {
                    continue;
                }
                $other = Student::whereRaw('LOWER(student_code) = ?', [$otherCode])->firstOrFail();
                Auth::forgetGuards();
                $this->withToken($token)->getJson('/api/my-grades?user_id='.$other->user_id)
                    ->assertOk()
                    ->assertJsonPath('data.student.carnet', $own->student_code);
            }
        }
    }

    public function test_only_the_original_browser_can_read_and_exchange_the_request(): void
    {
        $this->seed();
        $data = $this->authorize('2023-ISI-001');
        $this->approve($data);

        $this->withHeader('X-Approval-Nonce', str_repeat('a', 64))
            ->getJson('/api/qr-auth/status/'.$data['request_id'])
            ->assertForbidden();
        $this->exchange($data, str_repeat('a', 64), 403);

        $this->qrStatus($data)->assertJsonPath('status', 'approved');
        $this->exchange($data)->assertOk();
        $this->exchange($data, null, 409)->assertJsonPath('message', 'La aprobación todavía no fue confirmada.');
    }

    public function test_rejection_cancellation_and_expiry_never_create_a_session(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed();

        $rejected = $this->authorize('2024-ADM-001', 'google-cesar');
        $this->post(route('qr.reject'))->assertOk()->assertSee('Acceso rechazado');
        $this->qrStatus($rejected)->assertJsonPath('status', 'rejected');
        $this->exchange($rejected, null, 409);

        $cancelled = $this->requestQr('2022-ICI-001');
        $this->scan($cancelled);
        $this->cancel($cancelled)->assertOk()->assertJsonPath('message', 'Solicitud cancelada.');
        $this->qrStatus($cancelled)->assertJsonPath('status', 'cancelled');
        $this->get((string) parse_url($cancelled['qr_url'], PHP_URL_PATH))->assertOk()->assertSee('Solicitud cancelada');
        $this->exchange($cancelled, null, 409);

        $expired = $this->requestQr('2025-ENF-001');
        $this->scan($expired);
        $this->travel(config('qr_auth.ttl_minutes') + 1)->minutes();
        $this->qrStatus($expired)->assertJsonPath('status', 'expired');
        $this->get((string) parse_url($expired['qr_url'], PHP_URL_PATH))->assertOk()->assertSee('Solicitud caducada');
        $this->exchange($expired, null, 409);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_unknown_cross_owner_and_changed_identities_are_rejected(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed();

        $unknown = $this->requestQr('2024-MED-001');
        $this->scan($unknown);
        $this->googleCallback($this->startGoogle(), 'google-attacker')
            ->assertStatus(403)
            ->assertSee('Cuenta no autorizada');
        $this->qrStatus($unknown)->assertJsonPath('status', 'pending');
        $this->cancel($unknown)->assertOk();

        $crossOwner = $this->requestQr('2024-MED-001');
        $this->cancel($crossOwner);
        $crossOwner = $this->requestQr('2024-MED-001');
        $this->scan($crossOwner);
        $this->googleCallback($this->startGoogle(), 'google-cesar')
            ->assertStatus(403)
            ->assertSee('Cuenta no autorizada');
        $this->qrStatus($crossOwner)->assertJsonPath('status', 'pending');

        $this->googleCallback($this->startGoogle(), 'google-jose')->assertRedirect(route('qr.review'));
        $cloned = $this->requestQr('2023-ISI-001');
        $this->scan($cloned);
        $this->googleCallback($this->startGoogle(), 'google-jose-clon')
            ->assertStatus(403)
            ->assertSee('Cuenta no autorizada');
        $this->qrStatus($cloned)->assertJsonPath('status', 'pending');
    }

    public function test_bad_state_nonce_forged_and_reused_callbacks_are_rejected(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed();

        $badState = $this->requestQr('2024-MED-001');
        $this->scan($badState);
        $this->googleCallback($this->startGoogle(), 'google-jose', ['state' => 'estado-manipulado'])
            ->assertStatus(400)
            ->assertSee('Sesión de Google no válida');
        $this->qrStatus($badState)->assertJsonPath('status', 'pending');

        $badNonce = $this->requestQr('2023-ISI-001');
        $this->scan($badNonce);
        $this->googleCallback($this->startGoogle(), 'google-bad-nonce')
            ->assertStatus(401)
            ->assertSee('No se pudo verificar tu identidad');
        $this->qrStatus($badNonce)->assertJsonPath('status', 'pending');

        $this->flushSession();
        $forged = $this->get(route('qr.google.callback', ['code' => 'code-sin-sesion', 'state' => 'x']));
        $forged->assertStatus(400)->assertSee('Respuesta de Google no válida');

        $usedCode = 'code-reutilizable-1234';
        $first = $this->requestQr('2024-ADM-001');
        $this->scan($first);
        $this->googleCallback($this->startGoogle(), 'google-cesar', ['code' => $usedCode])
            ->assertRedirect(route('qr.review'));

        $second = $this->requestQr('2022-ICI-001');
        $this->scan($second);
        $this->googleCallback($this->startGoogle(), 'google-cesar', ['code' => $usedCode])
            ->assertStatus(400)
            ->assertSee('Código ya utilizado');
        $this->qrStatus($second)->assertJsonPath('status', 'pending');
        $this->assertSame(1, DB::table('qr_oauth_codes')->count());
    }

    public function test_consent_is_bound_to_the_verified_mobile_session_and_consumed_once(): void
    {
        $this->seed();
        $data = $this->requestQr('2024-MED-001');
        $this->scan($data);
        $this->googleCallback($this->startGoogle())->assertRedirect(route('qr.review'));
        $verifiedSession = session()->all();

        $this->flushSession();
        $this->get((string) parse_url($data['qr_url'], PHP_URL_PATH))->assertOk();
        $this->get(route('qr.review'))->assertForbidden();
        $this->approve($data, false)->assertForbidden();
        $this->qrStatus($data)->assertJsonPath('status', 'google_authenticated');

        $this->flushSession();
        $this->withSession($verifiedSession);
        $this->approve($data);
        $this->approve($data, false)->assertConflict();
        $this->exchange($data);
        $this->exchange($data, null, 409);
    }

    public function test_reassignment_and_removal_revoke_existing_qr_access(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed();
        $session = $this->completeLogin('2024-MED-001');
        $pending = $this->requestQr('2024-MED-001');
        $accounts = config('qr_auth.accounts');
        $accounts[1]['carnets'][] = '2024-med-001';
        config(['qr_auth.accounts' => $accounts]);
        $this->assertSame(1, Artisan::call('qr-google:sync-links'));
        $this->qrStatus($pending)->assertJsonPath('status', 'pending');

        unset($accounts[0]);
        config(['qr_auth.accounts' => array_values($accounts)]);
        $this->assertSame(0, Artisan::call('qr-google:sync-links'));
        $this->qrStatus($pending)->assertJsonPath('status', 'cancelled');
        $this->assertTrue(GooglePreauthorization::where('owner_label', 'jose')->firstOrFail()->revoked_at !== null);
        Auth::forgetGuards();
        $this->withToken($session['token'])->getJson('/api/my-grades')->assertUnauthorized();
        $this->completeLogin('2024-MED-001', 'google-cesar');
    }

    public function test_qr_poll_budget_does_not_consume_exchange_budget(): void
    {
        $this->seed();
        $data = $this->requestQr('2024-MED-001');
        for ($poll = 0; $poll < 40; $poll++) {
            $this->qrStatus($data)->assertOk();
        }
        $this->getJson('/api/qr-auth/status/'.$data['request_id'], [
            'X-Approval-Nonce' => $data['client_nonce'],
        ])->assertTooManyRequests();
        $this->scan($data);
        $this->googleCallback($this->startGoogle())->assertRedirect(route('qr.review'));
        $this->approve($data);
        $this->exchange($data);
    }

    public function test_admin_revocation_closes_sessions_and_future_authorizations(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed();

        $session = $this->completeLogin('2024-MED-001');
        $this->assertSame(0, Artisan::call('qr-google:revoke', ['carnet' => '2024-MED-001']));

        Auth::forgetGuards();
        $this->withToken($session['token'])->getJson('/api/my-grades')->assertUnauthorized();
        $this->assertNotNull(GooglePreauthorization::where('owner_label', 'jose')->firstOrFail()->revoked_at);

        $newRequest = $this->requestQr('2024-MED-001');
        $this->scan($newRequest);
        $this->googleCallback($this->startGoogle(), 'google-jose')
            ->assertStatus(403)
            ->assertSee('Cuenta no autorizada');
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_resyncing_links_does_not_restore_a_revocation(): void
    {
        $this->assertSame(0, Artisan::call('qr-google:revoke', ['carnet' => '2024-MED-001']));
        $this->assertSame(0, Artisan::call('qr-google:sync-links'));

        $this->assertNotNull(
            GooglePreauthorization::where('owner_label', 'jose')->firstOrFail()->revoked_at
        );
    }

    public function test_the_six_authorized_links_are_loaded_from_the_environment(): void
    {
        $env = Env::getRepository();
        $env->set('GOOGLE_ACCOUNT_LINKS', json_encode([
            ['owner' => 'uno', 'email' => 'uno@example.com', 'carnets' => '2024-MED-001,2023-ISI-001'],
            ['owner' => 'dos', 'email' => 'dos@example.com', 'carnets' => '2024-ADM-001,2022-ICI-001'],
            ['owner' => 'tres', 'email' => 'tres@example.com', 'carnets' => '2025-ENF-001,2023-MED-002'],
        ], JSON_THROW_ON_ERROR));

        try {
            $settings = (static fn () => require config_path('qr_auth.php'))();

            $this->assertTrue($settings['accounts_valid']);
            $this->assertCount(3, $settings['accounts']);
            $this->assertSame([
                '2024-med-001',
                '2023-isi-001',
                '2024-adm-001',
                '2022-ici-001',
                '2025-enf-001',
                '2023-med-002',
            ], $settings['protected_carnets']);
            $this->assertSame(
                ['2024-med-001', '2023-isi-001', '2024-adm-001', '2022-ici-001', '2025-enf-001', '2023-med-002'],
                array_merge(...array_column($settings['accounts'], 'carnets')),
            );
        } finally {
            $env->clear('GOOGLE_ACCOUNT_LINKS');
        }
    }

    public function test_google_links_require_an_explicit_empty_list_and_targeted_reactivation(): void
    {
        $this->seed();
        $accounts = config('qr_auth.accounts');
        $session = $this->completeLogin('2024-MED-001');
        $pending = $this->requestQr('2024-MED-001');
        $link = GooglePreauthorization::where('owner_label', 'jose')->firstOrFail();
        $originalSub = $link->google_sub;
        $env = \Illuminate\Support\Env::getRepository();

        foreach ([null, '', '{}', '[]'] as $raw) {
            $env->clear('GOOGLE_ACCOUNT_LINKS');
            if ($raw !== null) {
                $env->set('GOOGLE_ACCOUNT_LINKS', $raw);
            }
            $settings = (static fn () => require config_path('qr_auth.php'))();
            config(['qr_auth' => $settings]);
            $this->assertSame($raw === '{}' ? 1 : 0, Artisan::call('qr-google:sync-links'), json_encode($raw));
            $this->assertSame($raw === '[]', $link->refresh()->revoked_at !== null);
            $this->qrStatus($pending)->assertJsonPath('status', $raw === '[]' ? 'cancelled' : 'pending');
        }
        $env->clear('GOOGLE_ACCOUNT_LINKS');

        config(['qr_auth.accounts' => $accounts]);
        $this->assertSame(0, Artisan::call('qr-google:sync-links'));
        $this->assertNotNull($link->refresh()->revoked_at);
        $this->assertSame(0, Artisan::call('qr-google:sync-links', ['--reactivate' => '2024-MED-001']));
        $this->assertNull($link->refresh()->revoked_at);
        $this->assertSame($originalSub, $link->google_sub);
        $this->assertSame(1, Artisan::call('qr-google:sync-links', ['--reactivate' => '2024-MED-001']));
        Auth::forgetGuards();
        $this->withToken($session['token'])->getJson('/api/my-grades')->assertUnauthorized();
        $this->completeLogin('2024-MED-001');
    }

    public function test_public_qr_flow_hides_eligibility_until_an_authorized_identity_is_verified(): void
    {
        $this->seed();
        Student::where('student_code', '2023-ISI-001')->update(['status' => 'inactive']);
        $denials = [];
        $keys = [];
        foreach (['2024-MED-001', '2024-CON-001', '2023-ISI-001', '2024-XYZ-999'] as $carnet) {
            $data = $this->requestQr($carnet);
            $keys[] = array_keys($data);
            $this->scan($data)->assertSee('Continuar con Google');
            $this->qrStatus($data)->assertJsonPath('status', 'pending');
            $this->exchange($data, null, 409);
            $denials[] = $this->googleCallback($this->startGoogle(), 'google-attacker')
                ->assertForbidden()->getContent();
            $this->cancel($data)->assertOk();
        }
        $this->assertCount(1, array_unique(array_map('serialize', $keys)));
        $this->assertCount(1, array_unique($denials));
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
        $this->completeLogin('2024-MED-001');
    }

    public function test_duplicate_requests_traditional_paths_and_demo_mode_remain_protected(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed();

        $first = $this->requestQr('2024-MED-001');
        $this->postJson('/api/qr-auth/request', ['carnet' => '2024-MED-001'])->assertAccepted();
        $this->cancel($first)->assertOk();
        $this->requestQr('2024-MED-001');

        foreach (['email', 'correo'] as $field) {
            $this->postJson('/api/qr-auth/request', ['carnet' => '2024-MED-001', $field => 'atacante@example.com'])
                ->assertStatus(422);
        }
        $this->postJson('/api/qr-auth/request', ['carnet' => '2024-CON-001'])->assertAccepted();
        $this->postJson('/api/login-approvals/request', ['carnet' => '2024-MED-001'])->assertNotFound();

        foreach ([false, true] as $demo) {
            config(['student_access.demo' => $demo]);
            foreach (config('qr_auth.protected_carnets') as $code) {
                $student = Student::whereRaw('LOWER(student_code) = ?', [$code])->firstOrFail();
                // El acceso por carné sin contraseña ya no existe como ruta.
                $this->postJson('/api/login/carnet', ['carnet' => strtoupper($code)])->assertNotFound();
                $this->postJson('/api/login', ['email' => $student->user->email, 'password' => 'password123'])
                    ->assertUnprocessable();
                $this->postJson('/api/passkeys/login/options', ['carnet' => $code])->assertNotFound();

                foreach (['passkey', 'demo-session', 'chatbot-token', 'password-login', 'email-approval'] as $name) {
                    Auth::forgetGuards();
                    $this->withToken($student->user->createToken($name)->plainTextToken)
                        ->getJson('/api/my-grades')
                        ->assertUnauthorized();
                }
            }
        }

        config(['student_access.demo' => true]);
        // Las cuentas locales de demostración funcionan con carné + contraseña
        // individual, sin depender de Google.
        foreach (['2024-con-001', '2025-isi-002', '2022-arq-001', '2023-agr-001'] as $code) {
            $student = Student::whereRaw('LOWER(student_code) = ?', [$code])->firstOrFail();
            $student->user->forceFill(['password' => Hash::make('ClaveDePrueba#2026')])->save();

            $token = $this->postJson('/api/login', [
                'carnet' => $code,
                'password' => 'ClaveDePrueba#2026',
            ])->assertOk()->json('token');
            Auth::forgetGuards();
            $this->withToken($token)->getJson('/api/my-grades')->assertOk();
        }
        config(['student_access.demo' => false]);
        $this->postJson('/api/login/carnet', ['carnet' => '2024-CON-001'])->assertNotFound();
    }
}
