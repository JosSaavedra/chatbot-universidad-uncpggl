<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'career_id',
        'student_code',
        'year_of_study',
        'group_code',
        'status',
    ];

    protected $casts = [
        'year_of_study' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
