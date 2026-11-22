<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GooglePreauthorization;
use App\Models\QrAuthRequest;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class QrApprovalController extends Controller
{
    private const SESSION_REQUEST = 'qr_auth.request_id';
    private const SESSION_VERIFIED = 'qr_auth.verified';
    private const SESSION_OIDC_REQUEST = 'qr_auth.oidc_request';
    private const SESSION_NONCE = 'qr_google_nonce';

    private function secureView(string $view, array $data = [])
    {
        return response()->view($view, $data)
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function error(string $title, string $message, int $status = 400)
    {
        return $this->secureView('qr-auth.error', compact('title', 'message'))->setStatusCode($status);
    }

    /** Socialite cachea drivers: en cada petición hay que resolver uno con el Request actual. */
    private function googleDriver(): \App\Services\GoogleOidcProvider
    {
        Socialite::forgetDrivers();

        return Socialite::driver('google-qr')->enablePKCE();
    }

    private function findByToken(string $token): ?QrAuthRequest
    {
        return QrAuthRequest::where('qr_token_hash', hash('sha256', $token))->first();
    }

    private function expire(QrAuthRequest $row): QrAuthRequest
    {
        if (in_array($row->status, ['pending', 'google_authenticated'], true) && $row->expires_at->lt(now())) {
            QrAuthRequest::whereKey($row->id)->where('status', $row->status)->update(['status' => 'expired']);
        }

        return $row->refresh();
    }

    /** La solicitud activa vive en la sesión temporal del teléfono, no en React. */
    private function currentRequest(): ?QrAuthRequest
    {
        $id = session(self::SESSION_REQUEST);
        if (!is_string($id)) return null;

        $row = QrAuthRequest::where('public_id', $id)->first();

        return $row ? $this->expire($row) : null;
    }

    private function verifiedHere(QrAuthRequest $row): bool
    {
        return $row->google_sub !== null
            && session(self::SESSION_VERIFIED) === [$row->public_id, $row->google_sub];
    }

    public function show(string $token)
    {
        $row = $this->findByToken($token);
        if (!$row) {
            return $this->error('Enlace no válido', 'Este código QR no existe o ya no puede utilizarse.', 404);
        }

        $row = $this->expire($row);
        session([self::SESSION_REQUEST => $row->public_id]);

        if ($row->status === 'pending' || ($row->status === 'google_authenticated' && $this->verifiedHere($row))) {
            return $this->secureView('qr-auth.scan', [
                'carnet' => $row->carnet,
                'expiresAt' => $row->expires_at,
                'needsGoogle' => $row->status === 'pending',
            ]);
        }

        return $this->statusView($row);
    }

    private function statusView(QrAuthRequest $row)
    {
        $titles = [
            'approved' => 'Acceso autorizado',
            'rejected' => 'Acceso rechazado',
            'cancelled' => 'Solicitud cancelada',
            'expired' => 'Solicitud caducada',
            'consumed' => 'Solicitud ya utilizada',
        ];

        return $this->secureView('qr-auth.status', [
            'title' => $titles[$row->status] ?? 'Solicitud cerrada',
            'status' => $row->status,
        ]);
    }

    public function startGoogle()
    {
        $row = $this->currentRequest();
        if (!$row || $row->status !== 'pending') {
            return $this->error('Solicitud no disponible', 'Inicia una nueva solicitud desde el chatbot.', 409);
        }

        if (!config('services.google.client_id') || !config('services.google.client_secret')) {
            return $this->error('Google no está configurado', 'El administrador debe configurar las credenciales OAuth privadas.', 503);
        }

        if (app()->environment('production')
            && !str_starts_with((string) config('services.google.redirect'), 'https://')) {
            return $this->error('Callback no seguro', 'GOOGLE_REDIRECT_URI debe ser HTTPS en producción.', 503);
        }

        session([
            self::SESSION_NONCE => bin2hex(random_bytes(16)),
            self::SESSION_OIDC_REQUEST => $row->public_id,
        ]);

        try {
            return $this->googleDriver()->redirect();
        } catch (Throwable) {
            return $this->error('No se pudo iniciar Google', 'Vuelve a intentarlo desde el código QR.', 503);
        }
    }

    public function callback(Request $request)
    {
        $row = $this->currentRequest();
        $code = (string) $request->input('code', '');

        if (!$row || session(self::SESSION_OIDC_REQUEST) !== $row->public_id || strlen($code) < 8) {
            return $this->error('Respuesta de Google no válida', 'Vuelve a escanear el código QR.', 400);
        }

        try {
            $driver = $this->googleDriver();
            $driver->user();
            $claims = $driver->verifiedClaims();
        } catch (InvalidStateException) {
            return $this->error('Sesión de Google no válida', 'La protección contra CSRF rechazó la respuesta. Vuelve a intentarlo.', 400);
        } catch (Throwable) {
            return $this->error('No se pudo verificar tu identidad', 'Google no pudo confirmar esta solicitud.', 401);
        }

        // Sólo un code verificado se marca como usado: la restricción única resiste
        // dos respuestas simultáneas y evita que un state inválido queme el código.
        $codeHash = hash('sha256', $code);
        try {
            DB::table('qr_oauth_codes')->insert([
                'code_hash' => $codeHash,
                'request_id' => $row->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            return $this->error('Código ya utilizado', 'La autorización de Google sólo puede usarse una vez.', 400);
        }

        return DB::transaction(fn () => $this->bindVerifiedIdentity($request, $row, $claims), 3);
    }

    private function bindVerifiedIdentity(Request $request, QrAuthRequest $row, array $claims)
    {
        $row = $this->expire($row->refresh());
        if ($row->status !== 'pending') {
            return $this->statusView($row);
        }

        $email = strtolower((string) ($claims['email'] ?? ''));
        $sub = (string) ($claims['sub'] ?? '');
        $emailHash = hash('sha256', $email);

        $link = GooglePreauthorization::where('email_hash', $emailHash)->first();
        $studentIsEligible = Student::whereKey($row->student_id)
            ->whereRaw('LOWER(student_code) = ?', [strtolower($row->carnet)])
            ->where('status', 'active')->whereHas('user', fn ($user) => $user->where('is_admin', false))
            ->exists();
        if (!$studentIsEligible || !in_array(strtolower($row->carnet), config('qr_auth.protected_carnets'), true)
            || !$link || $link->revoked_at || $sub === '' || !$link->allows($row->carnet)
            || ($link->google_sub && !hash_equals($link->google_sub, $sub))) {
            return $this->error('Cuenta no autorizada', 'No se pudo autorizar este acceso. Verifica los datos o solicita ayuda.', 403);
        }

        if (!$link->google_sub) {
            $taken = GooglePreauthorization::where('google_sub', $sub)->exists();
            if ($taken) {
                return $this->error('Cuenta no autorizada', 'No se pudo autorizar este acceso. Verifica los datos o solicita ayuda.', 403);
            }
            $link->update(['google_sub' => $sub, 'linked_at' => now()]);
        }

        $row->update([
            'status' => 'google_authenticated',
            'google_sub' => $sub,
            'google_email_hash' => $emailHash,
        ]);

        $request->session()->regenerate(true);
        $request->session()->forget(self::SESSION_OIDC_REQUEST);
        $request->session()->put(self::SESSION_VERIFIED, [$row->public_id, $sub]);

        return redirect()->route('qr.review');
    }

    public function review()
    {
        $row = $this->currentRequest();
        if (!$row) {
            return $this->error('Solicitud no disponible', 'Inicia una nueva solicitud desde el chatbot.', 409);
        }
        if ($row->status !== 'google_authenticated') {
            return $this->statusView($row);
        }

        abort_unless($this->verifiedHere($row), 403);

        return $this->secureView('qr-auth.review', [
            'carnet' => $row->carnet,
            'expiresAt' => $row->expires_at,
        ]);
    }

    public function approve(Request $request)
    {
        $row = $this->currentRequest();
        if (!$row || $row->status !== 'google_authenticated') {
            return $this->error('Solicitud no disponible', 'Inicia una nueva solicitud desde el chatbot.', 409);
        }

        abort_unless($this->verifiedHere($row), 403);

        $data = $request->validate(['confirm_code' => 'required|digits:6']);
        if (!hash_equals($row->confirm_code, $data['confirm_code'])) {
            return $this->error('El número no coincide', 'Comprueba el número mostrado en la computadora e inténtalo de nuevo.', 403);
        }

        $link = GooglePreauthorization::where('google_sub', $row->google_sub)->first();
        if (!$link || $link->revoked_at || !$link->allows($row->carnet)) {
            return $this->error('Vinculación revocada', 'Inicia una nueva solicitud desde el chatbot.', 403);
        }

        $approved = QrAuthRequest::whereKey($row->id)
            ->where('status', 'google_authenticated')->where('google_sub', $row->google_sub)
            ->where('expires_at', '>', now())
            ->update(['status' => 'approved', 'decided_at' => now()]);
        abort_unless($approved === 1, 409);
        $request->session()->forget(self::SESSION_VERIFIED);

        return $this->statusView($row->refresh());
    }

    public function reject()
    {
        $row = $this->currentRequest();
        if (!$row || !in_array($row->status, ['pending', 'google_authenticated'], true)) {
            return $this->error('Solicitud no disponible', 'Inicia una nueva solicitud desde el chatbot.', 409);
        }

        if ($row->status === 'google_authenticated') {
            abort_unless($this->verifiedHere($row), 403);
        }
        abort_unless(QrAuthRequest::whereKey($row->id)->where('status', $row->status)
            ->update(['status' => 'rejected', 'decided_at' => now()]) === 1, 409);

        return $this->statusView($row->refresh());
    }
}
