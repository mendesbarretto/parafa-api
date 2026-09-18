<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyPg extends Model
{
    protected $connection = 'pgsql2';
    protected $table = 'companies';

    protected $casts = [
        'opening' => 'date',
        'date_situation' => 'date',
        'last_update' => 'date',
        'activities' => 'array',
    ];

    public function legalNature()
    {
        return $this->belongsTo(LegalNaturePg::class, 'legal_nature');
    }

    public function activity()
    {
        return $this->belongsTo(ActivitiesPg::class, 'main_activity');
    }
}