<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sede extends Model
{
    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'phone',
        'is_main',
        'is_active',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Carreras que se ofrecen en esta sede.
     */
    public function careers(): BelongsToMany
    {
        return $this->belongsToMany(Career::class, 'career_sede')->withTimestamps();
    }
}
