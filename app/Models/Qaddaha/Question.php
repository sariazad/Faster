<?php

namespace App\Models\Qaddaha;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Question extends Model
{
    public const LEVELS = [200, 400, 600];

    protected $table = 'questions';

    protected $casts = ['answers' => 'array', 'is_active' => 'boolean', 'level' => 'integer', 'times_used' => 'integer'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Picks $count questions, least used first (random among equals), skipping $exclude when possible.
     *
     * @return Collection<int, self>
     */
    public static function pick(?int $categoryId, int $level, int $count, array $exclude = [], array $notCategories = []): Collection
    {
        $base = static::query()->where('is_active', true)->where('level', $level)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($notCategories, fn ($q) => $q->whereNotIn('category_id', $notCategories));

        $fresh = (clone $base)->whereNotIn('id', $exclude ?: [0])->orderBy('times_used')->inRandomOrder()->limit($count)->get();
        if ($fresh->count() >= $count) {
            return $fresh;
        }

        // not enough unseen ones on this device: top up with the least used of the rest
        $more = (clone $base)->whereNotIn('id', $fresh->pluck('id')->all() ?: [0])->orderBy('times_used')->inRandomOrder()->limit($count - $fresh->count())->get();

        return $fresh->concat($more);
    }

    public function toGame(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'answers' => array_values($this->answers ?? []),
            'category' => $this->category?->name ?? '',
            'level' => $this->level,
        ];
    }
}
