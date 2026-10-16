<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Recibos de pago de EJEMPLO para los estudiantes ya registrados.
 *
 * Genera, por cada estudiante:
 *  - Una inscripción por cada año desde su ingreso hasta el año actual.
 *  - Las mensualidades del año en curso (algunas pagadas, otras pendientes
 *    y, en algunos casos, una vencida) para que la vista de "control de pagos"
 *    muestre información representativa.
 *
 * NO destructivo e idempotente: solo genera recibos para estudiantes que aún
 * no tienen ninguno, por lo que puede ejecutarse varias veces sin duplicar.
 *
 *   php artisan db:seed --class=PaymentSeeder
 *
 * NOTA: montos y fechas son de ejemplo; el administrador debe reemplazarlos
 * con los pagos reales.
 */
class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $students = Student::with('career')->orderBy('id')->get();

        $months = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
        ];

        $now = now();
        $currentYear = (int) $now->year;
        $currentMonth = (int) $now->month;
        $seq = (int) Payment::max('id') + 1;

        $rows = [];

        foreach ($students as $index => $student) {
            if ($student->payments()->exists()) {
                continue;
            }

            $career = $student->career;
            $enrollmentFee = (float) ($career->enrollment_fee ?? 0);
            $monthlyFee = (float) ($career->monthly_fee ?? 0);

            preg_match('/^(\d{4})/', (string) $student->student_code, $m);
            $admissionYear = isset($m[1])
                ? (int) $m[1]
                : ($currentYear - $student->year_of_study + 1);

            // --- Inscripciones por año ---
            for ($year = $admissionYear; $year <= $currentYear; $year++) {
                $rows[] = [
                    'student_id' => $student->id,
                    'receipt_number' => sprintf('REC-%d-%04d', $currentYear, $seq++),
                    'concept' => 'inscripcion',
                    'description' => "Inscripción académica {$year}",
                    'period' => "I-{$year}",
                    'amount' => $enrollmentFee,
                    'currency' => 'NIO',
                    'payment_method' => 'efectivo',
                    'status' => 'pagado',
                    'due_date' => "{$year}-01-15",
                    'paid_at' => "{$year}-01-20 09:30:00",
                    'reference' => "EFE-INS-{$year}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // --- Mensualidades del año en curso ---
            for ($month = 1; $month <= 12; $month++) {
                $mm = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
                $label = $months[$month - 1];

                $status = 'pendiente';
                $paidAt = null;

                if ($month < $currentMonth) {
                    $status = 'pagado';
                    $paidAt = "{$currentYear}-{$mm}-04 10:00:00";
                } elseif ($month === $currentMonth) {
                    $status = ($index % 3 === 2) ? 'pendiente' : 'pagado';
                    if ($status === 'pagado') {
                        $paidAt = "{$currentYear}-{$mm}-04 10:00:00";
                    }
                }

                // Algunos estudiantes arrastran una mensualidad vencida.
                $overdueMonth = $currentMonth - 2;
                if ($index % 5 === 1 && $overdueMonth >= 1 && $month === $overdueMonth) {
                    $status = 'vencido';
                    $paidAt = null;
                }

                $method = $index % 2 === 0 ? 'efectivo' : 'transferencia';

                $rows[] = [
                    'student_id' => $student->id,
                    'receipt_number' => sprintf('REC-%d-%04d', $currentYear, $seq++),
                    'concept' => 'mensualidad',
                    'description' => "Mensualidad de {$label} {$currentYear}",
                    'period' => "{$label} {$currentYear}",
                    'amount' => $monthlyFee,
                    'currency' => 'NIO',
                    'payment_method' => $paidAt ? $method : null,
                    'status' => $status,
                    'due_date' => sprintf('%04d-%02d-05', $currentYear, $month),
                    'paid_at' => $paidAt,
                    'reference' => $paidAt
                        ? (($method === 'efectivo' ? 'EFE' : 'TRF') . "-{$currentYear}{$mm}")
                        : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($rows)) {
            DB::table('payments')->insert($rows);
        }
    }
}
