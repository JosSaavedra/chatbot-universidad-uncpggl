<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = [
        'career_id',
        'teacher_id',
        'subject_name',
        'teacher_name',
        'day_of_week',
        'start_time',
        'end_time',
        'classroom',
        'building',
        'year_of_study',
        'group_code',
        'sede_id',
        'academic_period',
        'is_demo',
    ];

    protected $casts = [
        'year_of_study' => 'integer',
        'is_demo'       => 'boolean',
    ];

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Nombre del docente para mostrar: se prefiere el nombre completo del
     * docente relacionado y, si no existe, se usa el nombre desnormalizado.
     */
    public function getTeacherDisplayNameAttribute(): ?string
    {
        return $this->teacher?->full_name ?: $this->teacher_name;
    }
}
