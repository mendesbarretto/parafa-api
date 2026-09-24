<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecondaryActivitiesPg extends Model
{
    protected $connection = 'pgsql2';

    protected $table = 'secondary_activities';

    public function activities(): BelongsTo
    {
        return $this->belongsTo(ActivitiesPg::class, 'activity_id');
    }
}
