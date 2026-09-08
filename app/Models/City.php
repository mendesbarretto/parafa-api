<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $table = 'cities';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name', 'state', 'url', 'region'
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class, 'city_id');
    }

    public function scopeByState($query, $state)
    {
        return $query->where('state', $state);
    }
}
