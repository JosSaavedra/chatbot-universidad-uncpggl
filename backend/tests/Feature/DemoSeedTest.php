<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_nothing_when_the_explicit_flag_is_disabled(): void
    {
        config(['demo.seed_on_empty' => false]);

        $this->assertSame(0, Artisan::call('demo:ensure-seeded'));
        $this->assertDatabaseCount('users', 0);
        $this->assertStringContainsString('no está activo', Artisan::output());
    }

    public function test_it_seeds_an_empty_database_once_and_never_changes_passwords(): void
    {
        config(['demo.seed_on_empty' => true]);

        $this->assertSame(0, Artisan::call('demo:ensure-seeded'));
        $this->assertDatabaseCount('users', 10);
        $this->assertDatabaseCount('students', 10);
        $this->assertDatabaseCount('careers', 15);

        $hashes = User::query()->orderBy('id')->pluck('password', 'email')->all();
        $this->assertCount(10, $hashes);

        $this->assertSame(0, Artisan::call('demo:ensure-seeded'));
        $this->assertSame(
            $hashes,
            User::query()->orderBy('id')->pluck('password', 'email')->all(),
        );
        $this->assertDatabaseCount('users', 10);
        $this->assertDatabaseCount('students', 10);
        $this->assertStringContainsString('ya contiene datos', Artisan::output());
    }

    public function test_it_never_overwrites_an_existing_user_or_partial_database(): void
    {
        config(['demo.seed_on_empty' => true]);

        $user = User::create([
            'name' => 'Cuenta existente',
            'email' => 'existente@example.com',
            'password' => 'clave-existente',
        ]);
        $hash = $user->refresh()->password;
        config(['demo.student_password_hashes' => '{inválido']);

        $this->assertSame(0, Artisan::call('demo:ensure-seeded'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('students', 0);
        $this->assertSame($hash, $user->refresh()->password);
        $this->assertStringContainsString('ya contiene datos', Artisan::output());
    }

    public function test_it_imports_the_configured_hashes_only_for_the_seven_seeded_accounts(): void
    {
        $hashes = $this->demoHashes();
        config(['demo.seed_on_empty' => true, 'demo.student_password_hashes' => json_encode((object) $hashes)]);

        $this->assertSame(0, Artisan::call('demo:ensure-seeded'));
        $this->assertDatabaseCount('users', 10);
        foreach (Student::with('user')->get() as $student) {
            $code = $student->student_code;
            if (isset($hashes[$code])) {
                $this->assertSame($hashes[$code], $student->user->password);
                $this->assertTrue(Hash::check('Fixture-123!'.$code, $student->user->password));
                $this->assertStringNotContainsString($hashes[$code], Artisan::output());
            } else {
                $this->assertNotContains($student->user->password, $hashes);
            }
        }

        foreach (['2024-med-5837', '2024-adm-1748', '2025-enf-4621'] as $code) {
            $this->assertArrayHasKey($code, config('qr_auth.email_accounts'));
            $this->assertContains($code, config('qr_auth.protected_carnets'));
        }

        foreach (['2023-isi-9264', '2022-ici-8392', '2023-med-7056'] as $code) {
            $this->assertContains($code, config('qr_auth.protected_carnets'));
            $this->assertArrayNotHasKey($code, config('qr_auth.email_accounts'));
            $this->assertNotContains($code, array_map('strtolower', array_keys($hashes)));
        }

        $local = Student::where('student_code', '2024-CON-3189')->firstOrFail();
        $local->user->update(['password' => Hash::make('Administrator fixture update!')]);
        $changedHash = $local->user->password;
        $this->assertSame(0, Artisan::call('demo:ensure-seeded'));
        $this->assertSame($changedHash, $local->user->refresh()->password);
    }

    public function test_invalid_hash_maps_are_rejected_without_seeding(): void
    {
        $hashes = $this->demoHashes();
        $withProtected = $hashes;
        unset($withProtected['2024-CON-3189']);
        $withProtected['2023-ISI-9264'] = $hashes['2024-CON-3189'];
        config(['demo.seed_on_empty' => true]);

        foreach ([
            '{inválido',
            '[]',
            json_encode(array_slice($hashes, 1)),
            json_encode($withProtected),
            json_encode(array_replace($hashes, ['2024-CON-3189' => 'texto-ficticio'])),
            json_encode(array_replace($hashes, ['2024-CON-3189' => Hash::make('Weak cost fixture!', ['rounds' => 4])])),
            json_encode(array_replace($hashes, ['2024-CON-3189' => $hashes['2025-ISI-6473']])),
        ] as $invalid) {
            config(['demo.student_password_hashes' => $invalid]);

            $this->assertSame(1, Artisan::call('demo:ensure-seeded'));
            $this->assertStringContainsString('DEMO_STUDENT_PASSWORD_HASHES', Artisan::output());
            $this->assertStringNotContainsString('texto-ficticio', Artisan::output());
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('careers', 0);
        }
    }

    private function demoHashes(): array
    {
        Hash::driver('bcrypt')->setRounds(12);
        $hashes = [];
        foreach ([
            '2024-CON-3189',
            '2025-ISI-6473',
            '2022-ARQ-2815',
            '2023-AGR-9542',
            '2024-MED-5837',
            '2024-ADM-1748',
            '2025-ENF-4621',
        ] as $code) {
            $hashes[$code] = Hash::make('Fixture-123!'.$code);
        }

        return $hashes;
    }
}
