<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CnpjRequest extends Model
{
    protected $connection = 'pgsql2';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'correction_notified_at' => 'datetime',
    ];
}
