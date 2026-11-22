<?php

namespace App\Services;

use App\Models\QrAuthRequest;

final class QrLogin
{
    public static function requiresEmail(string $carnet): bool
    {
        return array_key_exists(strtolower($carnet), config('qr_auth.email_accounts'));
    }

    public static function publicUrl(string $path): string
    {
        $origin = rtrim((string) config('qr_auth.base_url'), '/');
        $parts = parse_url($origin);
        $schemes = app()->isProduction() ? ['https'] : ['http', 'https'];
        abort_unless(filter_var($origin, FILTER_VALIDATE_URL) && is_array($parts)
            && in_array($parts['scheme'] ?? '', $schemes, true)
            && array_diff(array_keys($parts), ['scheme', 'host', 'port']) === [],
            503, 'Configura una URL pública válida para verificar el acceso.');

        return $origin.$path;
    }

    public static function issue(string $carnet, int $studentId, array $binding = []): array
    {
        $email = $binding !== [];
        $qrToken = bin2hex(random_bytes(32));
        $clientNonce = bin2hex(random_bytes(32));
        $confirmCode = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes($email ? 5 : config('qr_auth.ttl_minutes'));
        $qrUrl = self::publicUrl(route($email ? 'qr.email.scan' : 'qr.scan', ['token' => $qrToken], false));

        $row = QrAuthRequest::create($binding + [
            'student_id' => $studentId,
            'public_id' => bin2hex(random_bytes(16)),
            'qr_token_hash' => hash('sha256', $qrToken),
            'client_hash' => hash('sha256', $clientNonce),
            'confirm_code' => $confirmCode,
            'carnet' => strtoupper($carnet),
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ]);

        return [
            'request_id' => $row->public_id,
            'client_nonce' => $clientNonce,
            'qr_url' => $qrUrl,
            'confirm_code' => $confirmCode,
            'expires_at' => $expiresAt->toISOString(),
        ];
    }
}
