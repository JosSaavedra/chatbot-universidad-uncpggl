<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\PasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

/**
 * Establece (o cambia) la contraseña individual de un estudiante.
 *
 * La contraseña se pide con entrada oculta y se confirma; nunca se imprime,
 * se registra ni se guarda en texto plano. Al cambiarla se revocan todos los
 * tokens del usuario, de modo que las sesiones anteriores dejan de ser
 * válidas de inmediato.
 */
class SetStudentPassword extends Command
{
    protected $signature = 'student:set-password {carnet : Carné del estudiante}';

    protected $description = 'Establece o cambia la contraseña individual de un estudiante (entrada oculta)';

    public function handle(PasswordPolicy $policy): int
    {
        $carnet = trim((string) $this->argument('carnet'));

        $student = Student::whereRaw('LOWER(student_code) = ?', [mb_strtolower($carnet)])->first();

        if (! $student || ! $student->user) {
            $this->error('No existe un estudiante con ese carné.');

            return self::FAILURE;
        }

        $user = $student->user;

        $password = (string) $this->secret('Nueva contraseña (mínimo '.PasswordPolicy::MIN_LENGTH.' caracteres)', false);
        $confirmation = (string) $this->secret('Confirme la contraseña', false);

        if ($password === '' || $confirmation === '') {
            $this->error('La contraseña y su confirmación son obligatorias.');

            return self::FAILURE;
        }

        if (! hash_equals($password, $confirmation)) {
            $this->error('Las contraseñas no coinciden.');

            return self::FAILURE;
        }

        $violations = $policy->violations($password, $user, $student);

        if ($violations !== []) {
            foreach ($violations as $violation) {
                $this->error($violation);
            }

            return self::FAILURE;
        }

        $hash = Hash::make($password);
        DB::transaction(function () use ($user, $hash) {
            $user->forceFill(['password' => $hash])->save();
            $user->tokens()->delete();
        }, 3);

        $this->info('Contraseña actualizada para el carné '.$student->student_code.'. Se revocaron las sesiones activas.');

        return self::SUCCESS;
    }
}
