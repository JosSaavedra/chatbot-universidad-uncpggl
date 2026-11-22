<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

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
    protected $signature = 'demo:ensure-seeded';

    protected $description = 'Siembra datos ficticios sólo si la base está vacía; nunca modifica registros existentes';

    /**
     * Tablas que el seeder llena. Si alguna tiene filas, la base no está vacía
     * y no se siembra para evitar duplicados o mezclas con datos existentes.
     */
    private const SEEDED_TABLES = [
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

        $seeded = DB::transaction(function (): bool {
            foreach (self::SEEDED_TABLES as $table) {
                if (Schema::hasTable($table) && DB::table($table)->exists()) {
                    return false;
                }
            }

            $status = $this->call('db:seed', ['--force' => true]);
            if ($status !== self::SUCCESS) {
                throw new RuntimeException('El seeder de datos ficticios terminó con error.');
            }

            return true;
        }, 3);

        if (! $seeded) {
            $this->line('La base ya contiene datos. No se modificó ningún registro.');

            return self::SUCCESS;
        }

        $this->info('Datos ficticios inicializados en una base vacía. Las contraseñas locales no se imprimen ni se reutilizan.');

        return self::SUCCESS;
    }
}
