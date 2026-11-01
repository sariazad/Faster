<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'image',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Adds q200_count / q400_count / q600_count with active questions per level.
     */
    public function scopeWithLevelCounts(Builder $query): Builder
    {
        $counts = [];

        foreach (Game::LEVELS as $points) {
            $counts["questions as q{$points}_count"] = fn (Builder $q) => $q
                ->where('is_active', true)
                ->where('points', $points);
        }

        return $query->withCount($counts);
    }

    /**
     * Active categories that have enough active questions for every level.
     *
     * @return Collection<int, Category>
     */
    public static function playable(): Collection
    {
        return static::query()
            ->active()
            ->withLevelCounts()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category) => $category->hasEnoughQuestions())
            ->values();
    }

    public function hasEnoughQuestions(): bool
    {
        foreach (Game::LEVELS as $points) {
            $attribute = "q{$points}_count";

            $count = $this->getAttribute($attribute) ?? $this->questions()
                ->where('is_active', true)
                ->where('points', $points)
                ->count();

            if ($count < Game::QUESTIONS_PER_LEVEL) {
                return false;
            }
        }

        return true;
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
