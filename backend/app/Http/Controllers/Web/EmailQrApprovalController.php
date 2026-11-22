<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\QrAuthRequest;
use App\Models\Student;
use App\Services\EmailQrApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class EmailQrApprovalController extends Controller
{
    private function view(string $view, array $data)
    {
        return response()->view('qr-auth.'.$view, $data)
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function pendingScan(string $token): QrAuthRequest
    {
        return QrAuthRequest::where('method', 'email')->where('qr_token_hash', hash('sha256', $token))
            ->where('status', 'pending')->where('expires_at', '>', now())->firstOrFail();
    }

    private function pendingReview(string $token): QrAuthRequest
    {
        $approval = QrAuthRequest::where('method', 'email')->where('email_token_hash', hash('sha256', $token))
            ->where('status', 'email_sent')->where('expires_at', '>', now())->firstOrFail();
        abort_unless(EmailQrApproval::matches($approval, Student::with('user')->find($approval->student_id)), 403);

        return $approval;
    }

    public function scan(string $token)
    {
        $this->pendingScan($token);

        return $this->view('email-scan', ['token' => $token]);
    }

    public function send(string $token, EmailQrApproval $email)
    {
        $email->send($this->pendingScan($token));

        return $this->view('status', [
            'title' => 'Solicitud enviada al correo vinculado',
            'status' => 'email_sent',
        ]);
    }

    public function review(Request $request, string $token)
    {
        $approval = $this->pendingReview($token);
        $choice = $request->query('opcion') === 'rechazar' ? 'reject' : 'approve';

        return $this->view('email-review', compact('approval', 'token', 'choice'));
    }

    public function decide(Request $request, string $token)
    {
        $approval = $this->pendingReview($token);
        $validation = Validator::make($request->all(), [
            'decision' => 'required|in:approve,reject',
            'confirm_code' => 'required_if:decision,approve|nullable|digits:6',
            'email' => 'prohibited',
            'correo' => 'prohibited',
        ]);
        if ($validation->fails()) {
            return $this->view('email-review', [
                'approval' => $approval,
                'token' => $token,
                'choice' => 'approve',
                'message' => 'Confirma la decisión con los datos indicados en el formulario.',
            ])->setStatusCode(422);
        }
        $data = $validation->validated();
        $approved = $data['decision'] === 'approve';
        if ($approved && ! hash_equals($approval->confirm_code, $data['confirm_code'])) {
            return $this->view('email-review', [
                'approval' => $approval,
                'token' => $token,
                'choice' => 'approve',
                'message' => 'El número de confirmación no coincide.',
            ])->setStatusCode(422);
        }

        $status = $approved ? 'approved' : 'rejected';
        abort_unless(QrAuthRequest::whereKey($approval->id)->where('status', 'email_sent')
            ->where('email_token_hash', hash('sha256', $token))->where('expires_at', '>', now())
            ->update(['status' => $status, 'decided_at' => now(), 'email_token_hash' => null]) === 1, 409);

        return $this->view('status', [
            'title' => $approved ? 'Acceso autorizado' : 'Acceso rechazado',
            'status' => $status,
        ]);
    }
}
