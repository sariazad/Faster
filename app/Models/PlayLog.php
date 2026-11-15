<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One played session of آخر القائمة or a ألف ياء team game (for the statistics page). */
class PlayLog extends Model
{
    public const AKHER = 'akher';

    public const ALEFYA = 'alefya';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'last_at' => 'datetime',
        'ended_at' => 'datetime',
        'finished' => 'boolean',
        'players' => 'integer',
        'rounds' => 'integer',
        'meta' => 'array',
    ];
}
