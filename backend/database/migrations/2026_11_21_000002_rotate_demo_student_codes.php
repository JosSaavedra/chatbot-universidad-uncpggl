<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODES = [
        '2024-med-001' => '2024-MED-5837',
        '2023-isi-001' => '2023-ISI-9264',
        '2024-adm-001' => '2024-ADM-1748',
        '2022-ici-001' => '2022-ICI-8392',
        '2025-enf-001' => '2025-ENF-4621',
        '2023-med-002' => '2023-MED-7056',
        '2024-con-001' => '2024-CON-3189',
        '2025-isi-002' => '2025-ISI-6473',
        '2022-arq-001' => '2022-ARQ-2815',
        '2023-agr-001' => '2023-AGR-9542',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $students = DB::table('students')->whereIn(DB::raw('LOWER(student_code)'), array_keys(self::CODES))
                ->lockForUpdate()->get(['id', 'user_id', 'student_code']);
            $studentIds = $students->pluck('id');
            $userIds = $students->pluck('user_id')->unique();

            foreach ($students as $student) {
                $new = self::CODES[strtolower($student->student_code)];
                if (DB::table('students')->whereRaw('LOWER(student_code) = ?', [strtolower($new)])->exists()) {
                    throw new RuntimeException('El carné de destino ya existe: '.$new);
                }

                DB::table('students')->where('id', $student->id)
                    ->update(['student_code' => $new, 'updated_at' => now()]);
            }

            foreach (self::CODES as $old => $new) {
                DB::table('conversations')->whereRaw('LOWER(student_code) = ?', [$old])
                    ->update(['student_code' => $new]);
            }

            foreach (DB::table('google_preauthorizations')->get(['id', 'carnets']) as $link) {
                $codes = json_decode($link->carnets, true, flags: JSON_THROW_ON_ERROR);
                $updated = array_map(static function (string $code): string {
                    $replacement = self::CODES[strtolower($code)] ?? null;

                    return $replacement === null ? $code : strtolower($replacement);
                }, $codes);
                if ($updated !== $codes) {
                    DB::table('google_preauthorizations')->where('id', $link->id)
                        ->update(['carnets' => json_encode(array_values(array_unique($updated)), JSON_THROW_ON_ERROR), 'updated_at' => now()]);
                }
            }

            DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\User')
                ->whereIn('tokenable_id', $userIds)->delete();
            DB::table('users')->whereIn('id', $userIds)->whereNotNull('remember_token')->update(['remember_token' => null]);
            DB::table('sessions')->whereIn('user_id', $userIds)->delete();

            $requests = DB::table('qr_auth_requests')->where(function ($query) use ($studentIds) {
                $query->whereIn('student_id', $studentIds)
                    ->orWhereIn(DB::raw('LOWER(carnet)'), array_keys(self::CODES));
            });
            $this->deleteQrSessions($requests->pluck('public_id')->all());
            $requests->whereIn('status', ['pending', 'google_authenticated', 'approved'])
                ->update(['status' => 'cancelled', 'decided_at' => now(), 'updated_at' => now()]);

            if (Schema::hasTable('login_approvals')) {
                DB::table('login_approvals')->whereIn('student_id', $studentIds)->where('status', 'pending')
                    ->update(['status' => 'cancelled', 'decided_at' => now(), 'updated_at' => now()]);
            }
        }, attempts: 3);
    }

    private function deleteQrSessions(array $requestIds): void
    {
        if ($requestIds === []) {
            return;
        }

        DB::table('sessions')->whereNull('user_id')->select('id', 'payload')
            ->chunkById(100, function ($sessions) use ($requestIds) {
                foreach ($sessions as $session) {
                    $payload = base64_decode($session->payload, true);
                    if ($payload === false) {
                        continue;
                    }
                    if (config('session.encrypt')) {
                        try {
                            $payload = Crypt::decryptString($payload);
                        } catch (DecryptException) {
                            continue;
                        }
                    }
                    foreach ($requestIds as $requestId) {
                        if (str_contains($payload, $requestId)) {
                            DB::table('sessions')->where('id', $session->id)->delete();
                            break;
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        throw new RuntimeException('La rotación es irreversible. Utiliza una copia de seguridad verificada.');
    }
};
