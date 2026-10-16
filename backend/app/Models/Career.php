<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Career extends Model
{
    protected $fillable = [
        'code',
        'name',
        'faculty',
        'duration_years',
        'modalidad',
        'enrollment_fee',
        'monthly_fee',
        'description',
        'titulo_otorga',
        'perfil_profesional',
        'campo_laboral',
        'requisitos_ingreso',
        'pensum_url',
        'imagen_url',
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

    /**
     * Sedes (CUR) donde se ofrece esta carrera.
     */
    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class, 'career_sede')->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(CareerSubject::class)->orderBy('year')->orderBy('period')->orderBy('name');
    }
}
