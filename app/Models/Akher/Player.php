<?php

namespace App\Models\Akher;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Player extends Model
{
    public $timestamps = false;

    protected $casts = ['spectator' => 'boolean', 'joined_at' => 'datetime'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_code');
    }
}
