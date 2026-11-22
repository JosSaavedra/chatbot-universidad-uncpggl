<?php

namespace Tests\Feature;

use App\Models\GooglePreauthorization;
use App\Models\QrAuthRequest;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoCarnetRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotation_preserves_rows_and_revokes_only_affected_access(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = Student::where('student_code', '2024-MED-5837')->firstOrFail();
        $student->update(['student_code' => '2024-MED-001']);
        $student->user->createToken('qr-google');
        $other = User::factory()->create();
        $other->createToken('password-login');

        $link = GooglePreauthorization::create([
            'email_hash' => hash('sha256', 'demo@example.test'),
            'carnets' => ['2024-med-001', '2023-isi-9264'],
            'google_sub' => 'example-sub',
            'linked_at' => now()->subDay(),
            'revoked_at' => now(),
        ]);
        $conversationId = DB::table('conversations')->insertGetId([
            'session_id' => 'rotation-test', 'user_id' => $student->user_id, 'student_code' => '2024-MED-001',
        ]);
        $request = QrAuthRequest::create([
            'student_id' => QrAuthRequest::NO_STUDENT,
            'public_id' => str_repeat('a', 32),
            'qr_token_hash' => str_repeat('b', 64),
            'client_hash' => str_repeat('c', 64),
            'confirm_code' => '123456',
            'carnet' => '2024-MED-001',
            'status' => 'approved',
            'expires_at' => now()->addMinute(),
        ]);
        DB::table('sessions')->insert([
            ['id' => 'desktop', 'user_id' => $student->user_id, 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'phone', 'user_id' => null, 'payload' => base64_encode(serialize(['qr_auth.request_id' => $request->public_id])), 'last_activity' => now()->timestamp],
            ['id' => 'other', 'user_id' => $other->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        $migration = require database_path('migrations/2026_11_21_000002_rotate_demo_student_codes.php');
        $migration->up();

        $this->assertDatabaseHas('students', ['id' => $student->id, 'user_id' => $student->user_id, 'student_code' => '2024-MED-5837']);
        $this->assertDatabaseHas('conversations', ['id' => $conversationId, 'student_code' => '2024-MED-5837']);
        $this->assertDatabaseHas('google_preauthorizations', [
            'id' => $link->id, 'email_hash' => $link->email_hash, 'google_sub' => $link->google_sub,
            'linked_at' => $link->getRawOriginal('linked_at'), 'revoked_at' => $link->getRawOriginal('revoked_at'),
        ]);
        $this->assertSame(['2024-med-5837', '2023-isi-9264'], $link->fresh()->carnets);
        $this->assertSame('cancelled', $request->fresh()->status);
        $this->assertSame(['other'], DB::table('sessions')->pluck('id')->all());
        $this->assertSame(0, $student->user->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
    }
}
