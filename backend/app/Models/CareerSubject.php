<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerSubject extends Model
{
    protected $table = 'career_subjects';

    protected $fillable = [
        'career_id',
        'year',
        'period',
        'code',
        'name',
        'credits',
    ];

    protected $casts = [
        'year' => 'integer',
        'credits' => 'integer',
    ];

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }
}
