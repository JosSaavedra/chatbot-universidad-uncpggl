<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Consulta de pagos y recibos del ESTUDIANTE AUTENTICADO.
     *
     * Requiere sesión (Sanctum) y devuelve únicamente los pagos asociados a la
     * cuenta del usuario. Si se envía `carnet`, se valida que coincida con el
     * propio; nunca se permite consultar el carné de otro estudiante.
     */
    public function lookup(Request $request): JsonResponse
    {
        $student = Student::with(['user', 'career'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'Tu cuenta no está vinculada a un registro de estudiante.',
            ], 404);
        }

        $carnet = $request->query('carnet');
        if (is_string($carnet) && trim($carnet) !== '') {
            if (mb_strtolower(trim($carnet)) !== mb_strtolower($student->student_code)) {
                return response()->json([
                    'message' => 'Solo puedes consultar tus propios pagos.',
                ], 403);
            }
        }

        $payments = Payment::where('student_id', $student->id)
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'concept' => $p->concept,
                'description' => $p->description,
                'period' => $p->period,
                'amount' => (float) $p->amount,
                'currency' => $p->currency,
                'payment_method' => $p->payment_method,
                'status' => $p->status,
                'due_date' => $p->due_date?->toDateString(),
                'paid_at' => $p->paid_at?->toDateTimeString(),
                'reference' => $p->reference,
            ]);

        $paidCount = $payments->where('status', 'pagado')->count();
        $pendingCount = $payments->whereIn('status', ['pendiente', 'vencido'])->count();
        $totalPaid = (float) $payments->where('status', 'pagado')->sum('amount');
        $totalPending = (float) $payments->whereIn('status', ['pendiente', 'vencido'])->sum('amount');

        return response()->json([
            'data' => [
                'student' => [
                    'name' => $student->user?->name,
                    'carnet' => $student->student_code,
                    'career' => $student->career?->name,
                    'faculty' => $student->career?->faculty,
                    'year_of_study' => $student->year_of_study,
                    'status' => $student->status,
                ],
                'summary' => [
                    'currency' => 'NIO',
                    'total_paid' => $totalPaid,
                    'total_pending' => $totalPending,
                    'total' => $totalPaid + $totalPending,
                    'count' => $payments->count(),
                    'paid_count' => $paidCount,
                    'pending_count' => $pendingCount,
                ],
                'payments' => $payments->values(),
            ],
        ]);
    }
}
