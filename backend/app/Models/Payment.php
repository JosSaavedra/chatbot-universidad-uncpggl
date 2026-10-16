<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'student_id',
        'receipt_number',
        'concept',
        'description',
        'period',
        'amount',
        'currency',
        'payment_method',
        'status',
        'due_date',
        'paid_at',
        'reference',
    ];

    protected $casts = [
        'amount' => 'float',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
