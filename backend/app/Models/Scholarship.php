<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scholarship extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'coverage_percentage',
        'status',
        'start_date',
        'end_date',
        'description',
    ];

    protected $casts = [
        'coverage_percentage' => 'float',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
