<?php

namespace Tests\Feature;

use App\Models\Career;
use App\Models\Conversation;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regresión de la Fase 2: acceso local con carné/correo + contraseña
 * individual, cierre del registro público y política explícita de tokens.
 */
class AuthCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'ClaveSegura#2026';

    private Career $career;

    protected function setUp(): void
    {
        parent::setUp();

        $this->career = Career::create([
            'name' => 'Carrera de prueba',
            'code' => 'TEST-001',
            'faculty' => 'Facultad de prueba',
            'duration_years' => 4,
            'enrollment_fee' => 100,
            'monthly_fee' => 100,
            'is_active' => true,
        ]);
    }

    private function makeStudent(
        string $code = '2024-TST-001',
        string $email = 'estudiante@example.com',
        string $password = self::PASSWORD,
        string $status = 'active'
    ): Student {
        $user = User::factory()->unverified()->create([
            'name' => 'Persona de Prueba',
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => false,
        ]);

        return Student::create([
            'user_id' => $user->id,
            'career_id' => $this->career->id,
            'student_code' => $code,
            'year_of_study' => 1,
            'status' => $status,
        ]);
    }

    public function test_login_with_carnet_and_password_succeeds(): void
    {
        $student = $this->makeStudent();

        $response = $this->postJson('/api/login', [
            'carnet' => '  '.strtolower($student->student_code).'  ',
            'password' => self::PASSWORD,
        ])->assertOk();

        $response->assertJsonPath('student.carnet', $student->student_code)
            ->assertJsonPath('user.email', $student->user->email)
            ->assertJsonStructure(['token', 'expires_at', 'idle_minutes']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $student->user_id,
            'name' => 'password-login',
        ]);
    }

    public function test_failed_token_issuance_rolls_back_revocation(): void
    {
        $student = $this->makeStudent();
        $user = $student->user;
        $password = 'Prueba previa segura 010!';
        $user->forceFill(['password' => \Illuminate\Support\Facades\Hash::make($password)])->save();
        $previous = $user->createToken('password-login')->accessToken;

        \Illuminate\Support\Facades\Event::listen(
            'eloquent.creating: '.\Laravel\Sanctum\PersonalAccessToken::class,
            static function () {
                throw new \RuntimeException('No fue posible emitir el token.');
            }
        );
        $this->postJson('/api/login', ['carnet' => $student->student_code, 'password' => $password])
            ->assertServerError();
        $this->assertModelExists($previous);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_login_with_email_and_password_succeeds_for_an_authorized_account(): void
    {
        $student = $this->makeStudent('2025-INS-001', 'cuenta.autorizada@example.com');

        $this->postJson('/api/login', [
            'email' => strtoupper($student->user->email),
            'password' => self::PASSWORD,
        ])->assertOk()
            ->assertJsonPath('student.carnet', $student->student_code)
            ->assertJsonPath('user.email', $student->user->email);
    }

    public function test_login_without_a_password_is_rejected_generically(): void
    {
        $student = $this->makeStudent();

        $this->postJson('/api/login', ['carnet' => $student->student_code])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Las credenciales no son correctas.',
                'errors' => ['carnet' => ['Las credenciales no son correctas.']],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_wrong_password_is_rejected_and_does_not_revoke_a_legitimate_session(): void
    {
        $student = $this->makeStudent();
        $token = $this->postJson('/api/login', [
            'carnet' => $student->student_code,
            'password' => self::PASSWORD,
        ])->assertOk()->json('token');

        $this->postJson('/api/login', [
            'carnet' => $student->student_code,
            'password' => 'ContraseñaIncorrecta#2026',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.carnet.0', 'Las credenciales no son correctas.');

        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', $student->user->email);
    }

    public function test_unknown_account_and_wrong_password_return_equivalent_responses(): void
    {
        $student = $this->makeStudent();

        $unknown = $this->postJson('/api/login', [
            'carnet' => '2024-NO-EXISTE',
            'password' => 'ContraseñaIncorrecta#2026',
        ]);
        $wrong = $this->postJson('/api/login', [
            'carnet' => $student->student_code,
            'password' => 'ContraseñaIncorrecta#2026',
        ]);

        $unknown->assertUnprocessable();
        $wrong->assertUnprocessable();
        $this->assertSame($unknown->json(), $wrong->json());
        $this->assertSame($unknown->headers->get('content-type'), $wrong->headers->get('content-type'));
    }

    public function test_an_inactive_student_can_neither_obtain_nor_keep_a_session(): void
    {
        $student = $this->makeStudent();
        $token = $student->user->createToken('password-login')->plainTextToken;

        $student->update(['status' => 'suspended']);

        $this->postJson('/api/login', [
            'carnet' => $student->student_code,
            'password' => self::PASSWORD,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.carnet.0', 'Las credenciales no son correctas.');

        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_qr_protected_student_cannot_bypass_qr_with_a_password(): void
    {
        $code = config('qr_auth.protected_carnets')[0];
        $student = $this->makeStudent(strtoupper($code), 'protegido@example.com');

        $this->postJson('/api/login', [
            'carnet' => $student->student_code,
            'password' => self::PASSWORD,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.carnet.0', 'Las credenciales no son correctas.');

        $forged = $student->user->createToken('password-login')->plainTextToken;
        Auth::forgetGuards();
        $this->withToken($forged)->getJson('/api/user')->assertUnauthorized();

        // La superficie QR sigue siendo la única vía admitida para ese carné.
        $this->postJson('/api/qr-auth/request', ['carnet' => $student->student_code])
            ->assertStatus(202)
            ->assertJsonStructure(['request_id', 'client_nonce', 'qr_url', 'confirm_code', 'expires_at']);
    }

    public function test_an_administrator_keeps_password_access(): void
    {
        $admin = User::factory()->unverified()->create([
            'name' => 'Administración de Prueba',
            'email' => 'admin@example.com',
            'password' => Hash::make(self::PASSWORD),
            'is_admin' => true,
        ]);

        $token = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => self::PASSWORD,
        ])->assertOk()->assertJsonPath('student', null)->json('token');

        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('is_admin', true);
    }

    public function test_changing_the_password_revokes_previous_tokens(): void
    {
        $student = $this->makeStudent();
        $oldToken = $student->user->createToken('password-login')->plainTextToken;
        $newPassword = 'NuevaClaveSegura#2026!';

        $this->artisan('student:set-password', ['carnet' => $student->student_code])
            ->expectsQuestion('Nueva contraseña (mínimo 12 caracteres)', $newPassword)
            ->expectsQuestion('Confirme la contraseña', $newPassword)
            ->assertSuccessful();

        $this->assertTrue(Hash::check($newPassword, $student->user->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $student->user_id,
            'id' => $oldToken,
        ]);
        $this->assertSame(0, $student->user->tokens()->count());
        $this->assertStringNotContainsString($newPassword, \Artisan::output());

        Auth::forgetGuards();
        $this->withToken($oldToken)->getJson('/api/user')->assertUnauthorized();

        $this->postJson('/api/login', [
            'carnet' => $student->student_code,
            'password' => $newPassword,
        ])->assertOk();
    }

    public function test_tokens_from_retired_or_unknown_methods_are_rejected(): void
    {
        $student = $this->makeStudent();

        foreach (['demo-session', 'chatbot-token', 'passkey', 'email-approval', 'sso-future'] as $name) {
            $token = $student->user->createToken($name)->plainTextToken;
            Auth::forgetGuards();
            $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
        }

        $allowed = $student->user->createToken('password-login')->plainTextToken;
        Auth::forgetGuards();
        $this->withToken($allowed)->getJson('/api/user')->assertOk();
    }

    public function test_seeder_leaves_no_known_password_and_never_overwrites_or_reactivates_accounts(): void
    {
        $this->seed();

        $users = User::orderBy('id')->get();
        $this->assertCount(10, $users);

        foreach ($users as $user) {
            $this->assertFalse(Hash::check('password123', $user->password));
            $this->assertFalse(Hash::check('', $user->password));
        }

        $this->assertCount(10, $users->pluck('password')->unique());

        $first = $users->first();
        $firstStudent = Student::where('user_id', $first->id)->firstOrFail();
        $firstStudent->update(['status' => 'suspended']);
        $first->update(['password' => Hash::make(self::PASSWORD)]);

        $this->seed();

        $this->assertTrue(Hash::check(self::PASSWORD, $first->fresh()->password));
        $this->assertSame('suspended', $firstStudent->fresh()->status);
    }

    public function test_seeded_emails_are_unique_individual_and_reserved(): void
    {
        $this->seed();

        $emails = User::orderBy('id')->pluck('email');
        $this->assertCount(10, $emails);
        $this->assertCount(10, $emails->unique());
        $this->assertSame(
            ['estudiante01@example.com', 'estudiante02@example.com', 'estudiante03@example.com', 'estudiante04@example.com', 'estudiante05@example.com', 'estudiante06@example.com', 'estudiante07@example.com', 'estudiante08@example.com', 'estudiante09@example.com', 'estudiante10@example.com'],
            $emails->values()->all()
        );

        $teacherEmails = Teacher::pluck('email');
        $this->assertNotEmpty($teacherEmails);
        $this->assertCount($teacherEmails->count(), $teacherEmails->unique());
        $this->assertTrue($teacherEmails->every(fn (string $email): bool => str_ends_with($email, '@example.com')));
    }

    public function test_seeded_accounts_are_not_marked_as_email_verified(): void
    {
        $this->seed();

        $this->assertSame(0, User::whereNotNull('email_verified_at')->count());
        $this->assertTrue(User::all()->every(fn (User $user): bool => $user->email_verified_at === null));
    }

    public function test_public_registration_is_closed(): void
    {
        $before = User::count();

        $this->postJson('/api/register', [
            'name' => 'Cuenta No Autorizada',
            'email' => 'no-autorizado@example.com',
            'password' => 'ContraseñaTemporal#2026',
            'password_confirmation' => 'ContraseñaTemporal#2026',
        ])->assertNotFound();

        $this->assertSame($before, User::count());
    }

    public function test_login_limit_is_independent_for_each_normalized_identifier(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/login', [
                'carnet' => $attempt % 2 === 0 ? '  2024-LIM-001  ' : '2024-lim-001',
                'password' => 'Incorrecta#2026',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'carnet' => '2024-LIM-001',
            'password' => 'Incorrecta#2026',
        ])->assertStatus(429);

        // Otro identificador desde la misma IP no agota el límite individual.
        $this->postJson('/api/login', [
            'carnet' => '2024-OTRO-001',
            'password' => 'Incorrecta#2026',
        ])->assertUnprocessable();
    }

    public function test_carnet_and_email_share_the_account_login_limit(): void
    {
        $student = $this->makeStudent();
        $carnet = ['carnet' => strtolower($student->student_code)];
        $email = ['email' => '  '.strtoupper($student->user->email).'  '];

        foreach ([$carnet, $email, $carnet, $email, $carnet] as $identifier) {
            $this->postJson('/api/login', $identifier + ['password' => 'Incorrecta#2026'])
                ->assertUnprocessable();
        }

        foreach ([$carnet, $email, ['carnet' => str_repeat('a', 41)] + $email, $carnet + ['email' => 'other@example.com']] as $identifier) {
            $this->postJson('/api/login', $identifier + ['password' => self::PASSWORD])
                ->assertStatus(429);
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_previous_seed_password_cannot_authenticate_existing_students(): void
    {
        $student = $this->makeStudent(password: 'password123');

        foreach ([['carnet' => $student->student_code], ['email' => $student->user->email]] as $identifier) {
            foreach (['password123', "password123\0suffix"] as $password) {
                $this->postJson('/api/login', $identifier + ['password' => $password])
                    ->assertUnprocessable();
            }
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_limit_is_applied_independently_to_the_source_ip(): void
    {
        for ($index = 1; $index <= 20; $index++) {
            $this->makeStudent(
                sprintf('2024-IP-%03d', $index),
                sprintf('ip%03d@example.com', $index),
            );

            $this->postJson('/api/login', [
                'carnet' => sprintf('2024-IP-%03d', $index),
                'password' => 'Incorrecta#2026',
            ])->assertUnprocessable();
        }

        $this->makeStudent('2024-IP-021', 'ip021@example.com');
        $this->postJson('/api/login', [
            'carnet' => '2024-IP-021',
            'password' => 'Incorrecta#2026',
        ])->assertStatus(429);
    }

    public function test_two_students_may_share_a_password_without_sharing_tokens(): void
    {
        $first = $this->makeStudent('2024-SH-001', 'compartido1@example.com');
        $second = $this->makeStudent('2024-SH-002', 'compartido2@example.com');

        $firstToken = $this->postJson('/api/login', [
            'carnet' => $first->student_code,
            'password' => self::PASSWORD,
        ])->assertOk()->json('token');
        $secondToken = $this->postJson('/api/login', [
            'carnet' => $second->student_code,
            'password' => self::PASSWORD,
        ])->assertOk()->json('token');

        $this->assertNotSame($firstToken, $secondToken);
        $this->assertSame($first->user_id, DBTokenOwner::id($firstToken));
        $this->assertSame($second->user_id, DBTokenOwner::id($secondToken));

        Auth::forgetGuards();
        $this->withToken($firstToken)->getJson('/api/user')
            ->assertOk()->assertJsonPath('email', $first->user->email);
        Auth::forgetGuards();
        $this->withToken($secondToken)->getJson('/api/user')
            ->assertOk()->assertJsonPath('email', $second->user->email);
    }

    public function test_a_student_cannot_read_another_students_private_data(): void
    {
        $first = $this->makeStudent('2024-PR-001', 'privado1@example.com');
        $second = $this->makeStudent('2024-PR-002', 'privado2@example.com');

        Grade::create([
            'user_id' => $first->user_id,
            'career_id' => $this->career->id,
            'subject_name' => 'Materia Privada Uno',
            'final_grade' => 90,
            'status' => 'approved',
            'period' => 'I-2026',
        ]);
        Grade::create([
            'user_id' => $second->user_id,
            'career_id' => $this->career->id,
            'subject_name' => 'Materia Privada Dos',
            'final_grade' => 80,
            'status' => 'approved',
            'period' => 'I-2026',
        ]);
        $conversation = Conversation::create([
            'session_id' => 'sesion-privada-ajena',
            'user_id' => $second->user_id,
            'title' => 'Conversación ajena',
        ]);

        $token = $this->postJson('/api/login', [
            'carnet' => $first->student_code,
            'password' => self::PASSWORD,
        ])->assertOk()->json('token');

        Auth::forgetGuards();
        $grades = $this->withToken($token)->getJson('/api/my-grades')->assertOk()->json('data.periods');
        $subjects = collect($grades)->pluck('subjects')->collapse();
        $this->assertTrue($subjects->contains('subject_name', 'Materia Privada Uno'));
        $this->assertFalse($subjects->contains('subject_name', 'Materia Privada Dos'));

        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/conversations/'.$conversation->id)->assertNotFound();
    }

    public function test_public_catalog_functions_remain_available_without_authentication(): void
    {
        $this->getJson('/api/auth/mode')->assertOk()->assertJsonStructure(['mode'])->assertJsonMissingPath('qr_required');
        $this->getJson('/api/careers')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/api/schedules/options')->assertOk();
        $this->getJson('/api/calendar?start=2026-01-01&end=2026-01-31')->assertOk();
    }

    public function test_qr_google_surface_remains_available_without_creating_a_password_session(): void
    {
        $this->seed();
        $code = config('qr_auth.protected_carnets')[0];

        $this->postJson('/api/qr-auth/request', ['carnet' => $code])->assertStatus(202);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $student = Student::whereRaw('LOWER(student_code) = ?', [$code])->firstOrFail();
        $this->postJson('/api/login', [
            'carnet' => $student->student_code,
            'password' => self::PASSWORD,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_token_allowlist_does_not_admit_unknown_or_future_methods(): void
    {
        $student = $this->makeStudent();

        foreach (['sso-future', 'oauth-oidc', 'qr-google'] as $name) {
            $token = $student->user->createToken($name)->plainTextToken;
            Auth::forgetGuards();
            $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
        }
    }
}

/**
 * Resuelve el propietario de un token Sanctum sin exponer el secreto en las
 * aserciones de la prueba.
 */
final class DBTokenOwner
{
    public static function id(string $plainTextToken): int
    {
        $publicId = explode('|', $plainTextToken, 2)[0] ?? '';

        return (int) DB::table('personal_access_tokens')
            ->where('id', $publicId)
            ->value('tokenable_id');
    }
}
