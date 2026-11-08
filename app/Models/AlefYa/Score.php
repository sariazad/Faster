<?php

namespace App\Models\AlefYa;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    protected $casts = ['won' => 'boolean'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Best first: wins, then right answers, then time left, then whoever got there first. */
    public function scopeRanked(Builder $query): Builder
    {
        return $query->orderByDesc('won')->orderByDesc('correct')->orderByDesc('time_left')->orderBy('id');
    }

    public function playerName(): string
    {
        return $this->account ? $this->account->masked() : 'زائر';
    }
}
