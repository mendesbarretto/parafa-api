<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivitiesPg extends Model
{
    protected $connection = 'pgsql2';
    protected $table = 'activities';
}