<?php

namespace App\Services;

use App\Mail\LoginApprovalMail;
use App\Models\QrAuthRequest;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class EmailQrApproval
{
    public static function recipient(string $carnet): ?string
    {
        $email = config('qr_auth.email_accounts')[strtolower($carnet)] ?? null;

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public static function binding(string $email, string $passwordHash): string
    {
        return hash_hmac('sha256', strtolower($email)."\0".$passwordHash, config('app.key'));
    }

    public static function matches(QrAuthRequest $row, ?Student $student): bool
    {
        $email = self::recipient($row->carnet);

        return $row->method === 'email' && $email && $student?->status === 'active'
            && $student->user && ! $student->user->is_admin
            && strcasecmp($student->student_code, $row->carnet) === 0
            && hash_equals((string) $row->email_binding_hash, self::binding($email, $student->user->password));
    }

    public function send(QrAuthRequest $row): void
    {
        $student = Student::with('user')->find($row->student_id);
        abort_unless(self::matches($row, $student), 403, 'La solicitud de acceso ya no es válida.');
        $email = self::recipient($row->carnet);
        $token = bin2hex(random_bytes(32));
        $url = QrLogin::publicUrl(route('qr.email.review', ['token' => $token], false));

        $key = 'qr-email:student:'.$row->student_id;
        $claimed = Cache::lock($key.':lock', 10)->get(function () use ($row, $key, $token) {
            abort_unless($row->fresh()->status === 'pending', 409, 'La solicitud ya se inició o se cerró.');
            abort_if(RateLimiter::tooManyAttempts($key, 3), 429, 'Espera antes de solicitar otro correo.');

            abort_unless(QrAuthRequest::whereKey($row->id)->where('status', 'pending')
                ->where('expires_at', '>', now())->update([
                    'status' => 'email_sent',
                    'email_token_hash' => hash('sha256', $token),
                ]) === 1, 409, 'La solicitud ya se inició o caducó.');

            RateLimiter::hit($key, 600);

            return true;
        });
        abort_unless($claimed, 429, 'Espera antes de solicitar otro correo.');

        try {
            Mail::mailer('qr-approval')->to($email)->send(new LoginApprovalMail($row, $url));
        } catch (Throwable) {
            // La excepción del proveedor puede contener el destinatario o el enlace.
            QrAuthRequest::whereKey($row->id)->where('status', 'email_sent')->update([
                'status' => 'delivery_failed',
                'email_token_hash' => null,
            ]);
            abort(503, 'No se pudo enviar la solicitud de verificación.');
        }
    }
}
