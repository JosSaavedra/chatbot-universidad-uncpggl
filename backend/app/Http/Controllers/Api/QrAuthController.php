<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GooglePreauthorization;
use App\Models\QrAuthRequest;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QrAuthController extends Controller
{
    private const NONCE_HEADER = 'X-Approval-Nonce';

    private string $headerName = self::NONCE_HEADER;

    private function browserNonce(Request $request): string
    {
        $nonce = $request->header($this->headerName);
        abort_unless(is_string($nonce) && strlen($nonce) >= 32 && strlen($nonce) <= 128, 403);

        return $nonce;
    }

    private function expireIfNeeded(QrAuthRequest $request): QrAuthRequest
    {
        if (in_array($request->status, ['pending', 'google_authenticated', 'approved'], true) && $request->expires_at->lte(now())) {
            QrAuthRequest::whereKey($request->id)->where('status', $request->status)->update(['status' => 'expired']);
        }

        return $request->refresh();
    }

    private function findForBrowser(Request $request): QrAuthRequest
    {
        $id = $request->validate(['request_id' => 'required|string|size:32'])['request_id'];
        $nonce = $this->browserNonce($request);
        $row = QrAuthRequest::where('public_id', $id)->first();

        abort_unless($row && hash_equals((string) $row->client_hash, hash('sha256', $nonce)), 403,
            'Esta solicitud pertenece a otro navegador.');

        return $this->expireIfNeeded($row);
    }

    public function request(Request $request): JsonResponse
    {
        abort_if($request->has('email') || $request->has('correo'), 422,
            'La identidad se verifica con Google desde tu teléfono.');

        $code = strtolower(trim($request->validate(['carnet' => 'required|string|max:40'])['carnet']));
        $origin = rtrim((string) config('qr_auth.base_url'), '/');
        $parts = parse_url($origin);
        $allowedSchemes = app()->environment('production') ? ['https'] : ['http', 'https'];
        abort_unless(filter_var($origin, FILTER_VALIDATE_URL) && is_array($parts)
            && in_array($parts['scheme'] ?? '', $allowedSchemes, true)
            && array_diff(array_keys($parts), ['scheme', 'host', 'port']) === [],
            503, 'Configura una URL pública válida para el acceso QR. En producción debe usar HTTPS.');

        $studentId = Student::whereRaw('LOWER(student_code) = ?', [$code])
            ->where('status', 'active')->whereHas('user', fn ($user) => $user->where('is_admin', false))
            ->value('id');
        $eligible = in_array($code, config('qr_auth.protected_carnets'), true);

        $qrToken = bin2hex(random_bytes(32));
        $clientNonce = bin2hex(random_bytes(32));
        $confirmCode = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(config('qr_auth.ttl_minutes'));

        $row = QrAuthRequest::create([
            'student_id' => $eligible ? ($studentId ?? QrAuthRequest::NO_STUDENT) : QrAuthRequest::NO_STUDENT,
            'public_id' => bin2hex(random_bytes(16)),
            'qr_token_hash' => hash('sha256', $qrToken),
            'client_hash' => hash('sha256', $clientNonce),
            'confirm_code' => $confirmCode,
            'carnet' => strtoupper($code),
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'request_id' => $row->public_id,
            'client_nonce' => $clientNonce,
            'qr_url' => $origin.route('qr.scan', ['token' => $qrToken], false),
            'confirm_code' => $confirmCode,
            'expires_at' => $expiresAt->toISOString(),
        ], 202);
    }

    public function status(Request $request, string $publicId): JsonResponse
    {
        $request->merge(['request_id' => $publicId]);
        $row = $this->findForBrowser($request);

        // Sin datos personales: sólo el estado que React necesita para reaccionar.
        return response()->json([
            'status' => $row->status,
            'expires_at' => $row->expires_at->toISOString(),
        ]);
    }

    public function exchange(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $row = $this->findForBrowser($request);

            abort_unless($row->status === 'approved', 409, 'La aprobación todavía no fue confirmada.');

            $locked = QrAuthRequest::where('id', $row->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'approved' && $locked->expires_at->gte(now()), 403,
                'La solicitud ya fue utilizada o caducó.');

            $link = GooglePreauthorization::where('google_sub', $locked->google_sub)->first();
            abort_unless($link && ! $link->revoked_at && $link->allows($locked->carnet), 403,
                'La vinculación con Google fue revocada. Inicia una nueva solicitud.');

            $student = Student::with('user')->find($locked->student_id);
            abort_unless(
                $student && $student->status === 'active' && $student->user && ! $student->user->is_admin
                    && strcasecmp($student->student_code, $locked->carnet) === 0
                    && in_array(strtolower($locked->carnet), config('qr_auth.protected_carnets'), true),
                403,
                'La cuenta de estudiante no está disponible.'
            );
            abort_unless(QrAuthRequest::whereKey($locked->id)->where('status', 'approved')
                ->where('expires_at', '>', now())
                ->update(['status' => 'consumed', 'consumed_at' => now()]) === 1, 409);

            $student->user->tokens()->delete();
            $token = $student->user->createToken('qr-google')->plainTextToken;

            return response()->json([
                'token' => $token,
                'student' => [
                    'carnet' => $student->student_code,
                    'year' => $student->year_of_study,
                    'group' => $student->group_code,
                ],
                'expires_at' => now()->addMinutes(config('sanctum.expiration'))->toISOString(),
                'idle_minutes' => config('student_access.idle_minutes'),
            ]);
        }, 3);
    }

    public function cancel(Request $request): JsonResponse
    {
        $row = $this->findForBrowser($request);

        abort_unless(in_array($row->status, ['pending', 'google_authenticated'], true), 409,
            'Esta solicitud ya no puede cancelarse.');

        abort_unless(QrAuthRequest::whereKey($row->id)->whereIn('status', ['pending', 'google_authenticated'])
            ->update(['status' => 'cancelled', 'decided_at' => now()]) === 1, 409);

        return response()->json(['message' => 'Solicitud cancelada.']);
    }
}
