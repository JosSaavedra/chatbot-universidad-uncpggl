<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GooglePreauthorization;
use App\Models\QrAuthRequest;
use App\Models\Student;
use App\Services\EmailQrApproval;
use App\Services\QrLogin;
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
        if (in_array($request->status, ['pending', 'email_sent', 'google_authenticated', 'approved'], true) && $request->expires_at->lte(now())) {
            QrAuthRequest::whereKey($request->id)->where('status', $request->status)->update(['status' => 'expired', 'email_token_hash' => null]);
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
        $studentId = Student::whereRaw('LOWER(student_code) = ?', [$code])
            ->where('status', 'active')->whereHas('user', fn ($user) => $user->where('is_admin', false))
            ->value('id');
        $eligible = ! QrLogin::requiresEmail($code) && in_array($code, config('qr_auth.protected_carnets'), true);

        return response()->json(QrLogin::issue($code, $eligible ? ($studentId ?? QrAuthRequest::NO_STUDENT)
            : QrAuthRequest::NO_STUDENT), 202);
    }

    public function sendEmail(Request $request, EmailQrApproval $approval): JsonResponse
    {
        $approval->send($this->findForBrowser($request));

        return response()->json(['status' => 'email_sent'], 202);
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

            $student = Student::with('user')->find($locked->student_id);
            if ($locked->method === 'email') {
                abort_unless(EmailQrApproval::matches($locked, $student), 403, 'La solicitud de acceso ya no es v?lida.');
                $method = 'qr-email';
            } else {
                $link = GooglePreauthorization::where('google_sub', $locked->google_sub)->first();
                abort_unless($link && ! $link->revoked_at && $link->allows($locked->carnet), 403,
                    'La vinculaci?n con Google fue revocada. Inicia una nueva solicitud.');
                abort_unless(
                    $student && $student->status === 'active' && $student->user && ! $student->user->is_admin
                        && strcasecmp($student->student_code, $locked->carnet) === 0
                        && ! QrLogin::requiresEmail($locked->carnet)
                        && in_array(strtolower($locked->carnet), config('qr_auth.protected_carnets'), true),
                    403,
                    'La cuenta de estudiante no est? disponible.'
                );
                $method = 'qr-google';
            }
            abort_unless(QrAuthRequest::whereKey($locked->id)->where('status', 'approved')
                ->where('expires_at', '>', now())
                ->update(['status' => 'consumed', 'consumed_at' => now()]) === 1, 409);

            $student->user->tokens()->delete();
            $token = $student->user->createToken($method)->plainTextToken;

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

        abort_unless(in_array($row->status, ['pending', 'email_sent', 'google_authenticated'], true), 409,
            'Esta solicitud ya no puede cancelarse.');

        abort_unless(QrAuthRequest::whereKey($row->id)->whereIn('status', ['pending', 'email_sent', 'google_authenticated'])
            ->update(['status' => 'cancelled', 'decided_at' => now(), 'email_token_hash' => null]) === 1, 409);

        return response()->json(['message' => 'Solicitud cancelada.']);
    }
}
