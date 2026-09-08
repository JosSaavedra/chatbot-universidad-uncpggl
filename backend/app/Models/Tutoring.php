<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tutoring extends Model
{
    protected $fillable = [
        'user_id',
        'subject_name',
        'tutor_name',
        'scheduled_at',
        'status',
        'meeting_link_or_place',
        'cost',
        'exam_cost',
        'tutoring_paid',
        'exam_paid',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'cost' => 'float',
        'exam_cost' => 'float',
        'tutoring_paid' => 'boolean',
        'exam_paid' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
