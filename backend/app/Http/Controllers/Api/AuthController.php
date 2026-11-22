<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Services\EmailQrApproval;
use App\Services\LoginIdentity;
use App\Services\QrLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Autenticación local: carné (o correo) + contraseña individual.
 *
 * Decisiones de seguridad:
 *
 *  - el carné por sí solo NO autentica: la contraseña siempre se comprueba
 *    con `Hash::check`;
 *  - todos los fallos devuelven el mismo mensaje, el mismo código (422) y la
 *    misma estructura, de modo que no se puede enumerar carnés ni distinguir
 *    "cuenta inexistente" de "contraseña incorrecta";
 *  - `Hash::check` se ejecuta siempre (contra un hash señuelo precalculado si
 *    la cuenta no existe) para que el tiempo de respuesta tampoco revele si la
 *    cuenta existe;
 *  - la identidad se toma siempre del token de Sanctum; nunca de parámetros
 *    enviados por el cliente;
 *  - las cuentas de carnés que exigen QR + Google no pueden evadir ese
 *    requisito iniciando sesión con contraseña.
 */
class AuthController extends Controller
{
    /**
     * Mensaje único para TODOS los fallos de autenticación.
     */
    private const INVALID_CREDENTIALS = 'Las credenciales no son correctas.';

    /**
     * Hash señuelo precalculado: se usa cuando la cuenta no existe o no puede
     * autenticarse, para igualar el coste de la comprobación real. Nunca
     * corresponde a una cuenta de la aplicación.
     */
    private const DUMMY_HASH = '$2y$12$w6CG/dti3v2exvX4cOZRr.6/JWo9v1AqIfZkCBUjO4TCflIMdxAKy';

    /**
     * Inicio de sesión con carné (o correo) y contraseña individual.
     */
    public function login(Request $request): JsonResponse
    {
        $identity = LoginIdentity::fromRequest($request);
        $password = $request->input('password');

        // Todos los fallos de credenciales (identificador ausente, contraseña
        // ausente o formato inválido) usan exactamente la misma respuesta.
        if ($identity->identifier === '' || ! is_string($password) || $password === ''
            || strlen($password) > 200 || str_contains($password, "\0")) {
            throw ValidationException::withMessages(['carnet' => [self::INVALID_CREDENTIALS]]);
        }

        $user = $identity->user;

        // La comprobación se ejecuta SIEMPRE: si no hay cuenta se usa un hash
        // señuelo, de modo que el tiempo de respuesta sea equivalente.
        $passwordMatches = Hash::check($password, $user?->password ?? self::DUMMY_HASH);

        // Las bases sembradas antes de la Fase 2 pueden conservar esta clave compartida.
        if (! $user || ! $passwordMatches || $password === 'password123' || ! $this->mayAuthenticate($user)) {
            throw ValidationException::withMessages(['carnet' => [self::INVALID_CREDENTIALS]]);
        }

        $student = Student::where('user_id', $user->id)->first();
        if ($student && QrLogin::requiresEmail($student->student_code)) {
            if (! $request->filled('carnet') || $request->hasAny(['email', 'correo']) || $user->is_admin) {
                throw ValidationException::withMessages(['carnet' => [self::INVALID_CREDENTIALS]]);
            }
            $email = EmailQrApproval::recipient($student->student_code);
            abort_unless($email, 503, 'La verificación por correo no está disponible.');

            return response()->json(['method' => 'email', 'carnet' => $student->student_code]
                + QrLogin::issue($student->student_code, $student->id, [
                    'method' => 'email',
                    'email_binding_hash' => EmailQrApproval::binding($email, $user->password),
                ]), 202);
        }

        // Sesión única: los tokens anteriores se revocan sólo tras autenticar
        // correctamente (un intento fallido nunca invalida una sesión legítima).
        $token = DB::transaction(function () use ($user) {
            $current = User::whereKey($user->id)->lockForUpdate()->first();
            if (!$current || $current->password !== $user->password || !$this->mayAuthenticate($current)
                || QrLogin::requiresEmail((string) Student::where('user_id', $current->id)->value('student_code'))) {
                throw ValidationException::withMessages(['carnet' => [self::INVALID_CREDENTIALS]]);
            }
            $current->tokens()->delete();

            return $current->createToken('password-login')->plainTextToken;
        }, 3);

        return response()->json([
            'user' => $user,
            'student' => $student ? [
                'carnet' => $student->student_code,
                'year' => $student->year_of_study,
                'group' => $student->group_code,
            ] : null,
            'token' => $token,
            'idle_minutes' => config('student_access.idle_minutes'),
            'expires_at' => now()->addMinutes(config('sanctum.expiration'))->toISOString(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    /**
     * Comprueba que la cuenta puede autenticarse con contraseña:
     *
     *  - una cuenta sin ficha de estudiante no accede;
     *  - los carnés que exigen QR + Google no pueden evadirlo con contraseña,
     *    incluso si la cuenta tiene el rol de administrador;
     *  - el estudiante debe estar activo.
     */
    private function mayAuthenticate(User $user): bool
    {
        $student = Student::where('user_id', $user->id)->first();

        if (! $student) {
            return false;
        }

        if (! QrLogin::requiresEmail($student->student_code) && $this->requiresQrGoogle($student)) {
            return false;
        }

        return $student->status === 'active';
    }

    /**
     * Un carné configurado en `qr_auth.protected_carnets` exige la ceremonia
     * QR + Google: la contraseña no es un método alternativo válido.
     */
    private function requiresQrGoogle(Student $student): bool
    {
        return in_array(
            mb_strtolower((string) $student->student_code),
            array_map('mb_strtolower', (array) config('qr_auth.protected_carnets', [])),
            true,
        );
    }
}
