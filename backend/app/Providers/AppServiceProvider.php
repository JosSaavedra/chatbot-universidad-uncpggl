<?php

namespace App\Providers;

use App\Models\Career;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\Scholarship;
use App\Models\Student;
use App\Models\Tutoring;
use App\Models\User;
use App\Policies\CareerPolicy;
use App\Policies\GradePolicy;
use App\Policies\SchedulePolicy;
use App\Policies\ScholarshipPolicy;
use App\Policies\TutoringPolicy;
use App\Services\GoogleOidcProvider;
use App\Services\GoogleOidcVerifier;
use App\Services\LoginIdentity;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // El E2E local simula Google sin red. Nunca se activa en producción.
        $verifier = (string) config('qr_auth.test_oidc_verifier');
        if ($verifier !== '' && ! app()->isProduction() && class_exists($verifier)) {
            $this->app->bind(GoogleOidcVerifier::class, $verifier);
        }
    }

    public function boot(): void
    {
        RateLimiter::for('qr-poll', static fn (Request $request) => [
            Limit::perMinute(40)->by('request:'.$request->ip().':'.$request->route('publicId')),
            Limit::perMinute(300)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('chat', static function (Request $request): array {
            $user = $request->user();

            return [
                $user ? Limit::perMinute(18)->by('account:'.$user->id)
                    : Limit::perMinute(10)->by('guest:'.$request->ip()),
                Limit::perMinute(30)->by('chat-ip:'.$request->ip()),
                Limit::perMinute(60)->by('chat-global-minute'),
                Limit::perDay(500)->by('chat-global-day'),
            ];
        });

        // Límites de intentos de inicio de sesión, independientes entre sí:
        //   - por cuenta, con el mismo contador para carné y correo;
        //   - por IP, para frenar el barrido de muchas cuentas desde un mismo
        //     origen.
        RateLimiter::for('login', function (Request $request) {
            $identity = LoginIdentity::fromRequest($request);
            $key = match (true) {
                $identity->user !== null => 'account:'.$identity->user->id,
                $identity->identifier !== '' => 'unknown:'.hash('sha256', $identity->identifier),
                default => 'missing:'.$request->ip(),
            };

            return [
                Limit::perMinute(5)->by('login:'.$key),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        // Autorización por modelo (patrón GradePolicy): se aplica en los
        // controladores, de modo que la restricción no dependa sólo del
        // middleware de rutas.
        Gate::policy(Grade::class, GradePolicy::class);
        Gate::policy(Career::class, CareerPolicy::class);
        Gate::policy(Schedule::class, SchedulePolicy::class);
        Gate::policy(Scholarship::class, ScholarshipPolicy::class);
        Gate::policy(Tutoring::class, TutoringPolicy::class);

        // Socialite es un proveedor diferido: se resuelve en boot, no en register.
        Socialite::extend('google-qr', function ($app) {
            $config = $app['config']['services.google'];

            return (new GoogleOidcProvider(
                $app['request'],
                $config['client_id'] ?? '',
                $config['client_secret'] ?? '',
                $config['redirect'] ?? '',
                $app->make(GoogleOidcVerifier::class)
            ))->enablePKCE();
        });

        Sanctum::authenticateAccessTokensUsing(static function ($token, bool $valid): bool {
            // Política EXPLICITA de métodos de autenticación autorizados. No se
            // admite ninguna regla genérica: un token sólo vale si su método
            // está en esta lista, de modo que los tokens antiguos emitidos
            // mediante el acceso por carné sin contraseña (y cualquier token
            // de un método futuro no previsto) queden rechazados.
            if (! $valid) {
                return false;
            }

            if (! in_array($token->name, ['password-login', 'qr-google'], true)) {
                return false;
            }

            // Límites de expiración e inactividad (se conservan).
            $lastActivity = $token->last_used_at ?? $token->created_at;
            if (! $lastActivity || ! $lastActivity->gt(now()->subMinutes(config('student_access.idle_minutes')))) {
                return false;
            }

            $user = User::find($token->tokenable_id);
            if (! $user) {
                return false;
            }

            $student = Student::where('user_id', $user->id)->first();
            $carnet = mb_strtolower((string) $student?->student_code);
            $requiresQr = in_array($carnet, array_map('mb_strtolower', (array) config('qr_auth.protected_carnets', [])), true);

            if ($token->name === 'qr-google') {
                // La ceremonia QR + Google sólo sirve para los carnés que la
                // exigen y para cuentas de estudiante activas (nunca
                // administradores).
                return $requiresQr && ! $user->is_admin && $student?->status === 'active';
            }

            // Los administradores legítimos conservan su acceso.
            if ($user->is_admin) {
                return true;
            }

            // Una cuenta de estudiante sin ficha, deshabilitada o protegida
            // por QR no puede autorizar un token de contraseña.
            return $student
                && $student->status === 'active'
                && ! $requiresQr;
        });
    }
}
