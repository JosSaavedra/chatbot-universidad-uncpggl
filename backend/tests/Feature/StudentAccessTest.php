<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Acceso de los estudiantes a sus propios datos.
 *
 * El acceso es carné + contraseña individual: el carné por sí solo nunca
 * autentica. En estas pruebas la contraseña se aprovisiona explícitamente
 * (como hace el administrador con `student:set-password`), porque el seeder
 * deja las cuentas de demostración sin ninguna contraseña utilizable.
 */
class StudentAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'ClaveDePrueba#2026';

    /**
     * Establece una contraseña individual para todas las cuentas sembradas.
     */
    private function provisionPasswords(string $password = self::PASSWORD): void
    {
        Student::with('user')->get()->each(
            fn (Student $student) => $student->user->forceFill(['password' => Hash::make($password)])->save()
        );
    }

    public function test_demo_mode_exposes_only_general_schedules_to_guests(): void
    {
        $this->seed();
        config(['student_access.demo' => true]);
        $student = Student::where('student_code', '2024-CON-001')->firstOrFail();
        $receiptId = $student->payments()->firstOrFail()->id;
        $identity = http_build_query(['user_id' => $student->user_id, 'carnet' => $student->student_code]);
        $privatePaths = [
            'my-schedule', 'my-grades', 'grades', 'grades/1',
            'student-payments', "student-payments/$receiptId/receipt",
            'scholarships', 'scholarships/1', 'tutorings', 'tutorings/1',
        ];

        foreach (['', '1|token-invalido'] as $token) {
            $headers = $token === '' ? [] : ['Authorization' => "Bearer $token"];
            foreach ($privatePaths as $path) {
                $this->getJson("/api/$path?$identity", $headers)->assertUnauthorized();
            }
            $this->postJson('/api/chat/auth', [
                'message' => 'Mis notas',
                'user_id' => $student->user_id,
                'carnet' => $student->student_code,
            ], $headers)->assertUnauthorized();
        }

        $this->getJson('/api/schedules/options')->assertOk();
        $this->getJson("/api/schedules/general?career_id={$student->career_id}&year=1&$identity")
            ->assertOk()
            ->assertJsonPath('data.personalized', false)
            ->assertJsonPath('data.student', null);
    }

    public function test_all_ten_demo_accounts_receive_only_their_own_records(): void
    {
        $this->seed();
        config(['student_access.demo' => true]);
        $this->provisionPasswords();
        $students = Student::with('payments')->get();
        $this->assertCount(10, $students);
        foreach ($students as $index => $student) {
            Auth::forgetGuards();
            $login = $this->postJson('/api/login', [
                'carnet' => $student->student_code,
                'password' => self::PASSWORD,
            ]);
            if (in_array(strtolower($student->student_code), config('qr_auth.protected_carnets'), true)) {
                // Los carnés protegidos exigen QR + Google: la contraseña no
                // es un método alternativo válido.
                $login->assertUnprocessable();
                // La ceremonia completa se prueba en QrGoogleAuthTest.
                $token = $student->user->createToken('qr-google')->plainTextToken;
            } else {
                $token = $login->assertOk()->json('token');
            }
            $this->withToken($token);
            Auth::forgetGuards();
            $foreign = $students[($index + 1) % 10];
            $payments = $this->getJson('/api/student-payments')->assertOk()->json('data.payments');
            $this->assertEqualsCanonicalizing($student->payments->modelKeys(), array_column($payments, 'id'));
            $receipt = $this->getJson('/api/student-payments/'.$payments[0]['id'].'/receipt')->assertOk()
                ->assertJsonPath('data.student.carnet', $student->student_code)
                ->assertJsonPath('data.payment.receipt_number', $payments[0]['receipt_number']);
            $this->assertStringContainsString('no-store', $receipt->headers->get('Cache-Control'));
            foreach ($students->except($student->id) as $other) {
                $this->getJson('/api/student-payments/'.$other->payments->first()->id.'/receipt')->assertNotFound();
            }
            $this->getJson('/api/student-payments?carnet='.$foreign->student_code)->assertForbidden();
            $periods = $this->getJson('/api/my-grades?user_id='.$foreign->user_id)->assertOk()
                ->assertJsonPath('data.student.carnet', $student->student_code)->json('data.periods');
            $this->assertEqualsCanonicalizing(Grade::where('user_id', $student->user_id)->pluck('id')->all(),
                collect($periods)->pluck('subjects')->collapse()->pluck('id')->all());
            foreach (['scholarships', 'tutorings'] as $resource) {
                foreach ($this->getJson('/api/'.$resource)->assertOk()->json('data') as $record) {
                    $this->assertEquals($student->user_id, $record['user_id']);
                }
            }
            // Cada iteración representa un cliente distinto.
            $this->travel(1)->minutes();
        }
    }

    public function test_modes_expiry_and_revocation(): void
    {
        $this->seed();
        $this->provisionPasswords();
        $s = Student::with('user')->where('student_code', '2024-CON-001')->firstOrFail();
        $receiptId = $s->payments()->firstOrFail()->id;
        $login = fn () => $this->postJson('/api/login', [
            'carnet' => $s->student_code,
            'password' => self::PASSWORD,
        ]);
        $this->assertFalse(config('student_access.demo'));
        // El acceso por carné sin contraseña ya no existe como ruta.
        $this->postJson('/api/login/carnet', ['carnet' => $s->student_code])->assertNotFound();
        $this->postJson('/api/login', ['email' => $s->user->email, 'password' => 'password123'])->assertUnprocessable();
        foreach (['student-payments', 'my-grades', 'my-schedule', 'student-payments/'.$receiptId.'/receipt'] as $path) {
            $denied = $this->getJson('/api/'.$path)->assertUnauthorized();
            $this->assertStringContainsString('no-store', $denied->headers->get('Cache-Control'));
        }
        config(['student_access.demo' => true]);
        $this->withToken($login()->assertOk()->json('token'));
        $this->getJson('/api/user')->assertOk();
        Auth::forgetGuards();
        config(['student_access.demo' => false]);
        $this->getJson('/api/user')->assertOk();
        Auth::forgetGuards();
        $this->postJson('/api/logout')->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/user')->assertUnauthorized();
        $this->withToken($login()->assertOk()->json('token'));
        $this->travel(config('student_access.idle_minutes') + 1)->minutes();
        Auth::forgetGuards();
        $this->getJson('/api/my-grades')->assertUnauthorized();
        $this->withToken($login()->assertOk()->json('token'));
        config(['student_access.idle_minutes' => config('sanctum.expiration') + 10]);
        $this->travel(config('sanctum.expiration') + 1)->minutes();
        Auth::forgetGuards();
        $this->getJson('/api/student-payments/'.$receiptId.'/receipt')->assertUnauthorized();
    }

    public function test_institutional_account_uses_its_password_not_the_demo_flow(): void
    {
        $this->seed();
        $student = Student::with('user')->firstOrFail();
        // Sólo el fixture SQLite: simula una cuenta aprovisionada por Registro.
        $student->update(['student_code' => 'INSTITUCIONAL-TEST']);
        $student->user->update(['password' => Hash::make('clave-individual-de-prueba')]);
        config(['student_access.demo' => true]);
        $this->postJson('/api/login/carnet', ['carnet' => $student->student_code])->assertNotFound();
        config(['student_access.demo' => false]);
        $this->getJson('/api/auth/mode')->assertOk()->assertJsonPath('mode', 'institutional');
        $token = $this->postJson('/api/login', [
            'email' => $student->user->email, 'password' => 'clave-individual-de-prueba',
        ])->assertOk()->assertJsonPath('student.carnet', $student->student_code)->json('token');
        $this->withToken($token)->getJson('/api/my-grades')->assertOk()
            ->assertJsonPath('data.student.carnet', $student->student_code);
    }
}
