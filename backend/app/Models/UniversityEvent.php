<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniversityEvent extends Model
{
    protected $fillable = [
        'title',
        'description',
        'location',
        'event_date',
        'organizer',
        'is_active',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'is_active' => 'boolean',
    ];
}
