<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;

/**
 * Política de contraseñas individuales.
 *
 * Reglas aplicadas por `student:set-password` (y reutilizables por el futuro
 * flujo de activación de cuentas):
 *
 *  - longitud mínima de 12 caracteres;
 *  - rechazo de contraseñas frecuentes o comprometidas (lista local, sin
 *    llamadas de red ni dependencias externas);
 *  - prohibición de contener datos de la cuenta (carné, correo o nombre);
 *  - exige al menos tres tipos de caracteres.
 *
 * No se almacenan ni se registran contraseñas en ningún momento: este servicio
 * sólo analiza la cadena recibida en memoria.
 */
class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    /**
     * Contraseñas frecuentes o que aparecen en filtraciones conocidas.
     * Lista local y estática: no depende de servicios externos ni de red.
     */
    private const COMMON_PASSWORDS = [
        '123456789012', '123456789123', '1234567890ab', '123456abcde',
        'password123', 'password1234', 'password12345', 'contrasena123',
        'contraseña123', 'qwertyuiop12', 'qwerty123456', 'administrador',
        'admin123456', 'bienvenido123', 'estudiante123', 'universidad123',
        'carnet123456', 'iloveyou1234', 'abc123456789', 'changeme123',
        'default1234', 'hola12345678', 'unica1234567', 'mexico123456',
        'nuncamayor123', 'mypassword123', 'secrete12345',
    ];

    /**
     * Devuelve la lista de reglas incumplidas (vacía si la contraseña es válida).
     *
     * @return array<int, string>
     */
    public function violations(string $password, ?User $user = null, ?Student $student = null): array
    {
        $errors = [];

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'La contraseña debe tener al menos '.self::MIN_LENGTH.' caracteres.';
        }

        $normalized = $this->squash($password);

        foreach (self::COMMON_PASSWORDS as $common) {
            if ($normalized !== '' && str_contains($normalized, $this->squash($common))) {
                $errors[] = 'Esa contraseña aparece en listas de contraseñas frecuentes o comprometidas.';
                break;
            }
        }

        foreach ($this->personalTokens($user, $student) as $token) {
            if ($token !== '' && str_contains($normalized, $token)) {
                $errors[] = 'La contraseña no debe contener datos de la cuenta (carné, correo o nombre).';
                break;
            }
        }

        if ($this->characterClasses($password) < 3) {
            $errors[] = 'Combina al menos tres tipos de caracteres (minúsculas, mayúsculas, dígitos y símbolos).';
        }

        return array_values(array_unique($errors));
    }

    /**
     * Fragmentos que identifican a la cuenta y que no deben formar parte de
     * la contraseña.
     *
     * @return array<int, string>
     */
    private function personalTokens(?User $user, ?Student $student): array
    {
        $tokens = [];

        if ($student) {
            $tokens[] = $this->squash((string) $student->student_code);
        }

        if ($user) {
            $email = (string) $user->email;
            $tokens[] = $this->squash($email);
            $tokens[] = $this->squash(explode('@', $email)[0] ?? '');
            $tokens[] = $this->squash((string) $user->name);

            foreach (preg_split('/\s+/u', trim((string) $user->name)) ?: [] as $namePart) {
                $tokens[] = $this->squash($namePart);
            }
        }

        return array_values(array_filter($tokens, fn (string $token): bool => mb_strlen($token) >= 4));
    }

    /**
     * Normaliza un texto para compararlo con la contraseña (sin espacios ni
     * signos, en minúsculas).
     */
    private function squash(string $value): string
    {
        return mb_strtolower(preg_replace('/[^a-z0-9áéíóúüñ]/iu', '', $value) ?? '');
    }

    /**
     * Número de clases de caracteres presentes (minúsculas, mayúsculas,
     * dígitos y símbolos).
     */
    private function characterClasses(string $password): int
    {
        $classes = 0;
        $classes += preg_match('/[a-z]/', $password) ? 1 : 0;
        $classes += preg_match('/[A-Z]/', $password) ? 1 : 0;
        $classes += preg_match('/\d/', $password) ? 1 : 0;
        $classes += preg_match('/[^a-zA-Z0-9]/', $password) ? 1 : 0;

        return $classes;
    }
}
