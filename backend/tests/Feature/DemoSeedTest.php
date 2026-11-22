<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

        $this->assertSame(0, Artisan::call('demo:ensure-seeded'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('students', 0);
        $this->assertSame($hash, $user->refresh()->password);
        $this->assertStringContainsString('ya contiene datos', Artisan::output());
    }
}
