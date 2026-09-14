<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerPhone extends Model
{
    use SoftDeletes;

    protected $table = 'customer_phones';

    protected $fillable = [
        'contract', 'customer_id', 'customer_record', 'address_id', 'type', 'ddd', 'phone',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }
}
