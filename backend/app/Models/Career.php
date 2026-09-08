<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Career extends Model
{
    protected $fillable = [
        'code',
        'name',
        'faculty',
        'duration_years',
        'enrollment_fee',
        'monthly_fee',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'duration_years' => 'integer',
        'enrollment_fee' => 'float',
        'monthly_fee' => 'float',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
