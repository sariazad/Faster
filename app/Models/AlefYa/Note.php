<?php

namespace App\Models\AlefYa;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    protected $casts = ['resolved' => 'boolean'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
