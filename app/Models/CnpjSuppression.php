<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CnpjSuppression extends Model
{
    protected $connection = 'pgsql2';

    protected $guarded = ['id'];

    public static function cacheVersion(): string
    {
        // Read the durable revision on every request; eviction cannot resurrect old cache entries.
        return (int) static::max('id').':'.CnpjRequest::where('status', 'resolved')->count();
    }
}
