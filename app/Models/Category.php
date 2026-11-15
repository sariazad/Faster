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

    /** Adds fq{points}_count / fresh_questions_count: active questions no game has used yet. */
    public function scopeWithFreshCounts(Builder $query): Builder
    {
        $counts = ['questions as fresh_questions_count' => fn (Builder $q) => $q->where('is_active', true)->where('times_used', 0)];

        foreach (Game::LEVELS as $points) {
            $counts["questions as fq{$points}_count"] = fn (Builder $q) => $q
                ->where('is_active', true)
                ->where('times_used', 0)
                ->where('points', $points);
        }

        return $query->withCount($counts);
    }

    /** How many more classic games this category can fill with questions nobody has played yet. */
    public function freshGames(): int
    {
        $games = PHP_INT_MAX;

        foreach (Game::LEVELS as $points) {
            $fresh = (int) ($this->getAttribute("fq{$points}_count") ?? $this->questions()
                ->where('is_active', true)->where('times_used', 0)->where('points', $points)->count());
            $games = min($games, intdiv($fresh, Game::QUESTIONS_PER_LEVEL));
        }

        return $games;
    }

    public function freshQuestionsCount(): int
    {
        return (int) ($this->getAttribute('fresh_questions_count')
            ?? $this->questions()->where('is_active', true)->where('times_used', 0)->count());
    }

    /** True when every question of the category has already been played (so the next game repeats). */
    public function isPlayedOut(string $mode = Game::CLASSIC): bool
    {
        return $mode === Game::TAQTAQ ? $this->freshQuestionsCount() === 0 : $this->freshGames() === 0;
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
            ->withFreshCounts()
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

    /** Active questions across all levels (uses withLevelCounts when loaded). */
    public function playableQuestionsCount(): int
    {
        if ($this->getAttribute('active_questions_count') !== null) {
            return (int) $this->getAttribute('active_questions_count');
        }

        $total = 0;

        foreach (Game::LEVELS as $points) {
            $total += (int) ($this->getAttribute("q{$points}_count") ?? 0);
        }

        return $total ?: $this->questions()->where('is_active', true)->count();
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
