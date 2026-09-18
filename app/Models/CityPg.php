<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityPg extends Model
{
    protected $connection = 'pgsql2';
    protected $table = 'cities';
    protected $keyType = 'string';
    public $incrementing = false;

    public function companies()
    {
        return $this->hasMany(CompanyPg::class, 'city_id');
    }
}