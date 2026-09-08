<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = [
        'career_id',
        'subject_name',
        'teacher_name',
        'day_of_week',
        'start_time',
        'end_time',
        'classroom',
        'year_of_study',
    ];

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }
}
