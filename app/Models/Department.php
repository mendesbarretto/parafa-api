<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'departments';

    protected $fillable = [
        'name', 'url',
    ];

    public function categories()
    {
        return $this->hasMany(Category::class, 'department_id');
    }
}
