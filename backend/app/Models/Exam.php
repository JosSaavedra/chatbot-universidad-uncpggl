<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exam extends Model
{
    protected $fillable = [
        'career_id',
        'subject_name',
        'exam_type',
        'scheduled_at',
        'classroom',
        'year_of_study',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }
}
