<?php

namespace App\Console\Commands;

use App\Models\GooglePreauthorization;
use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Procedimiento controlado para sustituir los correos de las cuentas locales
 * de demostración por direcciones ficticias, individuales y únicas del dominio
 * reservado example.com.
 *
 * Reglas de seguridad:
 *
 *  - sólo se toca `users.email` de los carnés listados en
 *    `student_access.demo_carnets`; las cuentas institucionales quedan intactas;
 *  - no se modifica ningún identificador interno (`users.id`), por lo que no
 *    se rompe ninguna referencia académica (notas, pagos, becas, tutorías);
 *  - se omiten las cuentas que tengan un vínculo OIDC, tanto por correo
 *    local como por carné preautorizado;
 *  - nunca se escriben contraseñas ni se reactivan cuentas deshabilitadas.
 */
class NormalizeDemoEmails extends Command
{
    protected $signature = 'demo:normalize-emails {--dry-run : Sólo muestra el plan sin escribir}';

    protected $description = 'Asigna correos ficticios únicos (@example.com) a las cuentas locales de demostración';

    public function handle(): int
    {
        $carnets = (array) config('student_access.demo_carnets', []);

        if ($carnets === []) {
            $this->error('No hay carnés de demostración configurados.');

            return self::FAILURE;
        }

        $updated = 0;
        $unchanged = 0;
        $skipped = 0;

        foreach (array_values($carnets) as $index => $carnet) {
            $target = $this->targetEmail($index);

            $student = Student::whereRaw('LOWER(student_code) = ?', [mb_strtolower((string) $carnet)])->first();

            if (! $student || ! $student->user) {
                $skipped++;

                continue;
            }

            $user = $student->user;

            // Una cuenta vinculada con Google/OIDC no se modifica: su correo
            // puede formar parte de una identidad institucional existente.
            if ($this->hasGoogleLink($user, $student)) {
                $skipped++;

                continue;
            }

            if (mb_strtolower((string) $user->email) === $target) {
                $unchanged++;

                continue;
            }

            // El destino debe ser único: nunca se pisa otra cuenta.
            if (User::whereRaw('LOWER(email) = ?', [$target])->where('id', '!=', $user->id)->exists()) {
                $this->warn('El correo '.$target.' ya pertenece a otra cuenta. Se omite.');
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line($student->student_code.' -> '.$target.' (simulado)');

                continue;
            }

            $user->forceFill(['email' => $target])->save();
            $updated++;
        }

        if ($this->option('dry-run')) {
            $this->info('Simulación terminada. No se modificó ningún correo.');

            return self::SUCCESS;
        }

        $this->info('Cuentas de demostración actualizadas: '.$updated.'. Sin cambios: '.$unchanged.'. Omitidas: '.$skipped.'.');

        return self::SUCCESS;
    }

    /**
     * Indica si la cuenta local ya participa en un vínculo Google/OIDC.
     */
    private function hasGoogleLink(User $user, Student $student): bool
    {
        $emailHash = hash('sha256', mb_strtolower(trim((string) $user->email)));

        if (GooglePreauthorization::where('email_hash', $emailHash)->exists()) {
            return true;
        }

        return GooglePreauthorization::query()->get()->contains(
            fn (GooglePreauthorization $link): bool => $link->allows((string) $student->student_code)
        );
    }

    /**
     * Correo ficticio de la cuenta, derivado del orden canónico de los carnés
     * de demostración (estudiante01@example.com, estudiante02@example.com, ...).
     */
    private function targetEmail(int $index): string
    {
        return 'estudiante'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'@example.com';
    }
}
