<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected static function booted(): void
    {
        static::addGlobalScope('published', function (Builder $query): void {
            $query->whereNotExists(function ($suppressed): void {
                $suppressed->selectRaw('1')->from('cnpj_suppressions')
                    ->whereColumn('cnpj_suppressions.cnpj', 'companies.cnpj');
            });
        });
    }

    public function secondaryActivities(): HasMany
    {
        return $this->hasMany(SecondaryActivitiesPg::class, 'company_id');
    }

    public function legalNature()
    {
        return $this->belongsTo(LegalNaturePg::class, 'legal_nature');
    }

    public function activity()
    {
        return $this->belongsTo(ActivitiesPg::class, 'main_activity');
    }
}
