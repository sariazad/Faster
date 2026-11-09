<?php

namespace App\Models\Akher;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guess extends Model
{
    public const UPDATED_AT = null;

    protected $casts = ['created_at' => 'datetime', 'points' => 'integer', 'item_rank' => 'integer'];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
