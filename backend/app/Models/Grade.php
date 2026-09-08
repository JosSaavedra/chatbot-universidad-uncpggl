<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    protected $fillable = [
        'user_id',
        'career_id',
        'subject_name',
        'first_partial',
        'second_partial',
        'final_exam',
        'final_grade',
        'status',
        'period',
    ];

    protected $casts = [
        'first_partial' => 'float',
        'second_partial' => 'float',
        'final_exam' => 'float',
        'final_grade' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }
}
