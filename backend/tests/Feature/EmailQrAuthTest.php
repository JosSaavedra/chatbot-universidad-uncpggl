<?php

namespace Tests\Feature;

use App\Mail\LoginApprovalMail;
use App\Models\Career;
use App\Models\QrAuthRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailQrAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Clave de prueba 123!';

    public static function recipients(): array
    {
        return [
            ['2024-MED-5837', 'one@example.com', false],
            ['2024-ADM-1748', 'two@example.com', true],
            ['2025-ENF-4621', 'three@example.com', true],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::recipients() as [$carnet, $email]) {
            config(['qr_auth.email_accounts.'.strtolower($carnet) => $email]);
        }
    }

    private function student(string $carnet, bool $admin = false): Student
    {
        $career = Career::firstOrCreate(['code' => 'OTP'], [
            'name' => 'Pruebas', 'faculty' => 'Pruebas', 'duration_years' => 4,
            'enrollment_fee' => 100, 'monthly_fee' => 100, 'is_active' => true,
        ]);

        return Student::create([
            'user_id' => User::factory()->create(['password' => self::PASSWORD, 'is_admin' => $admin])->id,
            'career_id' => $career->id,
            'student_code' => $carnet, 'year_of_study' => 1, 'status' => 'active',
        ]);
    }

    private function start(string $carnet): array
    {
        return $this->postJson('/api/login', ['carnet' => $carnet, 'password' => self::PASSWORD])
            ->assertAccepted()->assertJsonPath('method', 'email')->assertJsonMissingPath('token')->json();
    }

    private function browser(string $action, array $pending, ?string $nonce = null)
    {
        return $this->postJson('/api/qr-auth/'.$action, ['request_id' => $pending['request_id']], [
            'X-Approval-Nonce' => $nonce ?? $pending['client_nonce'],
        ]);
    }

    private function reviewPath(): string
    {
        return parse_url(Mail::sent(LoginApprovalMail::class)->last()->reviewUrl, PHP_URL_PATH);
    }

    #[DataProvider('recipients')]
    public function test_each_recipient_approves_only_the_originating_browser(string $carnet, string $email, bool $mobile): void
    {
        Mail::fake();
        $this->student($carnet);
        $pending = $this->start($carnet);
        $this->assertStringNotContainsString($email, json_encode($pending));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Mail::assertNothingSent();
        $this->browser('exchange', $pending)->assertConflict();

        if ($mobile) {
            $this->browser('email/send', $pending, str_repeat('x', 64))->assertForbidden();
            $this->browser('email/send', $pending)->assertAccepted();
            $this->browser('email/send', $pending)->assertConflict();
        } else {
            $scan = parse_url($pending['qr_url'], PHP_URL_PATH);
            $this->get($scan)->assertOk()->assertDontSee($pending['confirm_code']);
            $this->post($scan)->assertOk();
            $this->post($scan)->assertNotFound();
        }
        Mail::assertSent(LoginApprovalMail::class, fn ($mail) => $mail->hasTo($email) && count($mail->to) === 1);
        Mail::assertSentCount(1);
        $review = $this->reviewPath();
        $this->get($review)->assertOk()->assertSee($carnet)->assertSee('Confirmar y autorizar');
        $this->browser('exchange', $pending)->assertConflict();
        $this->postJson($review, ['decision' => 'approve'])->assertUnprocessable();
        $wrongCode = $pending['confirm_code'] === '100000' ? '100001' : '100000';
        $this->post($review, ['decision' => 'approve', 'confirm_code' => $wrongCode])->assertUnprocessable();
        $this->post($review, ['decision' => 'approve', 'confirm_code' => $pending['confirm_code']])
            ->assertOk()->assertSee('Acceso autorizado');
        $this->post($review, ['decision' => 'approve', 'confirm_code' => $pending['confirm_code']])->assertNotFound();

        $otherBrowser = $this->start($carnet);
        $this->browser('exchange', $pending, $otherBrowser['client_nonce'])->assertForbidden();
        $this->browser('exchange', $otherBrowser)->assertConflict();
        $token = $this->browser('exchange', $pending)->assertOk()->assertJsonPath('student.carnet', $carnet)->json('token');
        $this->browser('exchange', $pending)->assertConflict();
        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token])->assertOk();
    }

    public function test_wrong_password_wrong_recipient_and_old_methods_fail_closed(): void
    {
        Mail::fake();
        $student = $this->student('2024-MED-5837');
        $this->postJson('/api/login', ['carnet' => $student->student_code, 'password' => 'incorrecta'])->assertUnprocessable();
        $this->postJson('/api/login', ['carnet' => $student->student_code, 'email' => 'invalid@example.net', 'password' => self::PASSWORD])
            ->assertUnprocessable();
        $this->postJson('/api/login', ['email' => $student->user->email, 'password' => self::PASSWORD])->assertUnprocessable();
        foreach (['password-login', 'qr-google', 'legacy'] as $name) {
            $token = $student->user->createToken($name)->plainTextToken;
            Auth::forgetGuards();
            $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        }
        $google = $this->postJson('/api/qr-auth/request', ['carnet' => $student->student_code])->assertAccepted()->json();
        $this->assertDatabaseHas('qr_auth_requests', ['public_id' => $google['request_id'], 'student_id' => QrAuthRequest::NO_STUDENT]);
        $this->browser('email/send', $google)->assertForbidden();

        $pending = $this->start($student->student_code);
        $this->browser('email/send', $pending)->assertAccepted();
        $review = $this->reviewPath();
        $this->postJson($review, ['decision' => 'approve', 'confirm_code' => $pending['confirm_code'], 'email' => 'invalid@example.net'])
            ->assertUnprocessable();
        config(['qr_auth.email_accounts.2024-med-5837' => 'another@example.net']);
        $this->post($review, ['decision' => 'approve', 'confirm_code' => $pending['confirm_code']])->assertForbidden();
        $this->browser('exchange', $pending)->assertConflict();
        $student->user->update(['is_admin' => true]);
        $this->postJson('/api/login', ['carnet' => $student->student_code, 'password' => self::PASSWORD])->assertUnprocessable();
    }

    public function test_rejection_expiry_and_mail_rate_limit(): void
    {
        Mail::fake();
        $this->student('2025-ENF-4621');
        $rejected = $this->start('2025-ENF-4621');
        $this->browser('email/send', $rejected)->assertAccepted();
        $review = $this->reviewPath();
        $this->get($review.'?opcion=rechazar')->assertOk()->assertSee('Confirmar rechazo');
        $this->post($review, ['decision' => 'reject'])->assertOk();
        $this->browser('exchange', $rejected)->assertConflict();

        $expired = $this->start('2025-ENF-4621');
        $this->browser('email/send', $expired)->assertAccepted();
        $expiredReview = $this->reviewPath();
        $third = $this->start('2025-ENF-4621');
        $this->browser('email/send', $third)->assertAccepted();
        $blocked = $this->start('2025-ENF-4621');
        $this->browser('email/send', $blocked)->assertTooManyRequests();
        Mail::assertSentCount(3);

        $this->travel(5)->minutes();
        $this->post($expiredReview, ['decision' => 'approve', 'confirm_code' => $expired['confirm_code']])->assertNotFound();
        $this->browser('exchange', $expired)->assertConflict();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_laravel_mailer_dispatches_through_https(): void
    {
        config([
            'services.gmail.client_id' => 'desktop-client',
            'services.gmail.client_secret' => 'desktop-secret',
            'services.gmail.refresh_token' => 'test-refresh-token',
            'mail.from.address' => 'sender@example.com',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token']),
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'test-message-id']),
        ]);
        $this->student('2024-MED-5837');
        $pending = $this->start('2024-MED-5837');
        $this->browser('email/send', $pending)->assertAccepted();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token' && $request['client_id'] === 'desktop-client'
            && $request['client_secret'] === 'desktop-secret' && $request['refresh_token'] === 'test-refresh-token');
        Http::assertSent(function ($request) use ($pending) {
            if ($request->url() !== 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send') {
                return false;
            }
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $request['raw']);
            $mime = quoted_printable_decode(base64_decode(strtr($request['raw'], '-_', '+'), true));

            return $request->hasHeader('Authorization', 'Bearer test-access-token')
                && preg_match('/^To: one@example\.com\r?$/m', $mime) === 1
                && preg_match('/^From: .*sender@example\.com>?\r?$/m', $mime) === 1
                && str_contains($mime, '2024-MED-5837')
                && ! str_contains($mime, $pending['client_nonce']);
        });
    }

    public function test_gmail_oauth_rejection_closes_the_request_without_sending(): void
    {
        config(['services.gmail' => [
            'client_id' => 'desktop-client', 'client_secret' => 'desktop-secret', 'refresh_token' => 'expired-token',
        ]]);
        Http::preventStrayRequests();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response([
            'error' => 'invalid_grant', 'error_description' => 'información privada del proveedor',
        ], 400)]);
        $this->student('2024-MED-5837');
        $pending = $this->start('2024-MED-5837');
        $this->browser('email/send', $pending)->assertServiceUnavailable()
            ->assertDontSee('información privada del proveedor');
        $this->browser('exchange', $pending)->assertConflict();
        $this->assertDatabaseHas('qr_auth_requests', [
            'public_id' => $pending['request_id'], 'status' => 'delivery_failed', 'email_token_hash' => null,
        ]);
        Http::assertSentCount(1);
    }

    public function test_the_four_password_students_keep_their_access(): void
    {
        foreach (['2024-CON-3189', '2025-ISI-6473', '2022-ARQ-2815', '2023-AGR-9542'] as $carnet) {
            $this->student($carnet);
            $this->postJson('/api/login', ['carnet' => $carnet, 'password' => self::PASSWORD])
                ->assertOk()->assertJsonPath('student.carnet', $carnet)->assertJsonStructure(['token']);
        }
        $this->assertDatabaseCount('qr_auth_requests', 0);
    }
}
