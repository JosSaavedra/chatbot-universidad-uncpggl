<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Inicializa los datos ficticios en una base SQLite vacía.
 *
 * Este comando existe para Render Free, donde no hay Shell ni disco
 * persistente. Es explícito (DEMO_SEED_ON_EMPTY=true), idempotente y seguro:
 *
 *  - sólo escribe si TODAS las tablas sembradas están vacías;
 *  - si existe cualquier registro, no ejecuta el seeder ni toca contraseñas;
 *  - el seeder, además, retorna sin cambios cuando ya hay usuarios;
 *  - no imprime, genera ni guarda contraseñas en claro.
 */
class EnsureDemoData extends Command
{
    private const PASSWORD_CARNETS = [
        '2024-CON-3189',
        '2025-ISI-6473',
        '2022-ARQ-2815',
        '2023-AGR-9542',
        '2024-MED-5837',
        '2024-ADM-1748',
        '2025-ENF-4621',
    ];

    /** Carnés protegidos que además usan contraseña y autorización por correo. */
    private const EMAIL_PASSWORD_CARNETS = [
        '2024-MED-5837',
        '2024-ADM-1748',
        '2025-ENF-4621',
    ];

    /** Carnés que conservan Google: nunca reciben un hash sembrado. */
    private const GOOGLE_ONLY_CARNETS = [
        '2023-ISI-9264',
        '2022-ICI-8392',
        '2023-MED-7056',
    ];

    protected $signature = 'demo:ensure-seeded';

    protected $description = 'Siembra datos ficticios sólo si la base está vacía; nunca modifica registros existentes';

    /**
     * Datos de aplicación que impiden tratar SQLite como una demo nueva.
     */
    private const DATA_TABLES = [
        'users',
        'students',
        'careers',
        'schedules',
        'exams',
        'grades',
        'scholarships',
        'tutorings',
        'academic_calendars',
        'university_events',
        'sedes',
        'payments',
        'teachers',
        'career_sede',
        'career_subjects',
        'admission_items',
        'conversations',
        'messages',
        'personal_access_tokens',
        'google_preauthorizations',
        'qr_auth_requests',
        'qr_oauth_codes',
        'login_approvals',
        'student_passkeys',
    ];

    public function handle(): int
    {
        if (! config('demo.seed_on_empty')) {
            $this->line('DEMO_SEED_ON_EMPTY no está activo. No se modificó la base.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('users')) {
            $this->error('La tabla users no existe. Ejecuta las migraciones antes de sembrar.');

            return self::FAILURE;
        }

        try {
            $seeded = DB::transaction(function (): ?bool {
                foreach (self::DATA_TABLES as $table) {
                    if (Schema::hasTable($table) && DB::table($table)->exists()) {
                        return false;
                    }
                }

                $hashes = $this->validatedPasswordHashes();
                if ($hashes === null) {
                    return null;
                }

                $seeder = $this->laravel->make(DatabaseSeeder::class)
                    ->setContainer($this->laravel)->setCommand($this);
                Model::unguarded(fn () => $seeder->__invoke(['passwordHashes' => $hashes]));

                return true;
            }, 3);
        } catch (QueryException) {
            // QueryException incluye los parámetros de SQL, que pueden contener hashes.
            $this->error('No se pudo guardar la inicialización de la demo. Se revirtieron los cambios.');

            return self::FAILURE;
        }

        if ($seeded === null) {
            $this->error('DEMO_STUDENT_PASSWORD_HASHES debe contener un hash bcrypt válido y distinto para cada uno de los siete carnés permitidos.');

            return self::FAILURE;
        }

        if ($seeded === false) {
            $this->line('La base ya contiene datos. No se modificó ningún registro.');

            return self::SUCCESS;
        }

        $this->info('Datos ficticios inicializados en una base vacía.');

        return self::SUCCESS;
    }

    private function validatedPasswordHashes(): ?array
    {
        $raw = config('demo.student_password_hashes');
        if ($raw === null || $raw === '') {
            return [];
        }
        if (! is_string($raw)) {
            return null;
        }
        $decoded = json_decode($raw);
        if (! $decoded instanceof \stdClass) {
            return null;
        }

        $hashes = get_object_vars($decoded);
        $protected = array_map('strtolower', (array) config('qr_auth.protected_carnets', []));
        $allowedProtected = array_map('strtolower', self::EMAIL_PASSWORD_CARNETS);
        $forbidden = array_values(array_unique(array_merge(
            array_map('strtolower', self::GOOGLE_ONLY_CARNETS),
            array_diff($protected, $allowedProtected),
        )));

        if (count($hashes) !== count(self::PASSWORD_CARNETS)
            || array_diff(array_keys($hashes), self::PASSWORD_CARNETS)
            || array_intersect(array_map('strtolower', array_keys($hashes)), $forbidden)) {
            return null;
        }

        foreach ($hashes as $hash) {
            if (! is_string($hash)
                || ! preg_match('~\A\$2y\$(?:1[2-9]|[23][0-9])\$[./A-Za-z0-9]{53}\z~', $hash)
                || ! Hash::verifyConfiguration($hash)) {
                return null;
            }
        }

        return count(array_unique($hashes)) === count($hashes) ? $hashes : null;
    }
}
