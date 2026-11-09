<?php

namespace App\Models\Akher;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListItem extends Model
{
    public $timestamps = false;

    protected $casts = ['aliases' => 'array', 'rank' => 'integer'];

    public function list(): BelongsTo
    {
        return $this->belongsTo(GameList::class, 'list_id');
    }
}
