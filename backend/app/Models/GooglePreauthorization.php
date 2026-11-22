<?php

namespace App\Models;

use App\Services\QrLogin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GooglePreauthorization extends Model
{
    protected $table = 'google_preauthorizations';

    protected $guarded = [];

    protected $casts = [
        'carnets' => 'array',
        'linked_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];

    // Call inside the transaction that changes the authorized owners.
    public static function invalidateAccess(array $carnets): void
    {
        $students = Student::whereIn(DB::raw('LOWER(student_code)'), $carnets)
            ->get(['id', 'user_id', 'student_code'])
            ->reject(fn (Student $student) => QrLogin::requiresEmail($student->student_code));

        (new User)->tokens()->getRelated()->newQuery()
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', $students->pluck('user_id')->filter())
            ->delete();

        QrAuthRequest::whereIn('student_id', $students->modelKeys())
            ->where('method', 'google')
            ->whereIn('status', ['pending', 'google_authenticated', 'approved'])
            ->update(['status' => 'cancelled', 'decided_at' => now()]);
    }

    public function allows(string $carnet): bool
    {
        return in_array(strtolower($carnet), array_map('strtolower', $this->carnets ?? []), true);
    }
}
