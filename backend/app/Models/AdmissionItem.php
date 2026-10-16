<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionItem extends Model
{
    protected $fillable = [
        'section',
        'content',
        'sort_order',
        'is_demo',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_demo'    => 'boolean',
    ];
}
