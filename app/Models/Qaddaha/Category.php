<?php

namespace App\Models\Qaddaha;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** A category is playable when every level has enough questions for both teams' columns. */
    public const MIN_PER_LEVEL = 2;

    protected $table = 'categories';

    protected $casts = ['is_active' => 'boolean', 'sort' => 'integer'];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function scopeWithLevelCounts(Builder $q): Builder
    {
        return $q->withCount([
            'questions as q200_count' => fn ($w) => $w->where('is_active', true)->where('level', 200),
            'questions as q400_count' => fn ($w) => $w->where('is_active', true)->where('level', 400),
            'questions as q600_count' => fn ($w) => $w->where('is_active', true)->where('level', 600),
        ]);
    }

    /** Active categories that can fill a full board. */
    public static function playable()
    {
        return static::query()->where('is_active', true)->withLevelCounts()->orderBy('sort')->orderBy('id')->get()
            ->filter(fn (self $c) => min($c->q200_count, $c->q400_count, $c->q600_count) >= self::MIN_PER_LEVEL)
            ->values();
    }
}
