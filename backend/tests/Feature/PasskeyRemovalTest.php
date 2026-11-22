<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PasskeyRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_passkey_routes_and_command_are_gone(): void
    {
        $this->seed();

        foreach (['register/options', 'register/verify', 'login/options', 'login/verify'] as $path) {
            $this->postJson('/api/passkeys/'.$path, ['carnet' => '2024-MED-001'])
                ->assertNotFound();
        }

        $this->assertFalse(array_key_exists('passkey:activar', Artisan::all()));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('student_passkeys'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('login_approvals'));
    }

    public function test_old_passkey_tokens_never_bypass_qr_google(): void
    {
        $this->seed();

        foreach (config('qr_auth.protected_carnets') as $code) {
            $student = Student::whereRaw('LOWER(student_code) = ?', [$code])->firstOrFail();

            foreach (['passkey', 'demo-session', 'chatbot-token', 'password-login', 'email-approval'] as $name) {
                Auth::forgetGuards();
                $this->withToken($student->user->createToken($name)->plainTextToken)
                    ->getJson('/api/my-grades')
                    ->assertUnauthorized();
            }
        }
    }
}
