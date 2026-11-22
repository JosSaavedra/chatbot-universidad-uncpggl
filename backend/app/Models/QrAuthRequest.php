<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrAuthRequest extends Model
{
    // A request with no eligible student follows the same public QR flow.
    public const NO_STUDENT = 0;

    protected $table = 'qr_auth_requests';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'immutable_datetime',
        'decided_at' => 'immutable_datetime',
        'consumed_at' => 'immutable_datetime',
    ];
}
