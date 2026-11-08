<?php

namespace App\Models\AlefYa;

use App\Support\AlefYaLetters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Question extends Model
{
    protected $casts = [
        'options' => 'array',
        'is_active' => 'boolean',
        'times_used' => 'integer',
    ];

    protected static function booted(): void
    {
        // The letter always follows the answer, unless an admin set it by hand.
        static::saving(function (Question $q) {
            if (! $q->letter || $q->isDirty('answer') && ! $q->isDirty('letter')) {
                $q->letter = AlefYaLetters::of($q->answer) ?? $q->letter;
            }
        });
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * A question for the letter that isn't in $exclude (least used first, then random).
     * When the letter has nothing left, starts over and reports reset=true.
     *
     * @param  array<int>  $exclude
     * @return array{0: ?Question, 1: bool}
     */
    public static function pick(?string $letter, array $exclude = []): array
    {
        $base = static::active()->when($letter, fn ($q) => $q->where('letter', $letter));

        $question = (clone $base)->whereNotIn('id', $exclude)->orderBy('times_used')->inRandomOrder()->first();
        $reset = false;

        if (! $question) {
            $question = (clone $base)->orderBy('times_used')->inRandomOrder()->first();
            $reset = (bool) $question;
        }

        $question?->increment('times_used');

        return [$question, $reset];
    }

    /** Four answers that all start with the same letter, the right one included. */
    public function choices(): array
    {
        $options = collect($this->options ?? [])->filter()->unique()->values();

        if (! $options->contains($this->answer)) {
            $options = $options->take(3)->push($this->answer);
        }

        if ($options->count() < 4) {
            $more = static::active()->where('letter', $this->letter)->where('id', '!=', $this->id)
                ->inRandomOrder()->limit(10)->pluck('answer')
                ->reject(fn ($a) => $options->contains($a))->unique();
            $options = $options->merge($more)->take(4);
        }

        return $options->shuffle()->values()->all();
    }

    /** What the screens need (the answer included: the host reads it out). */
    public function toGame(bool $withChoices = false): array
    {
        return array_filter([
            'id' => $this->id,
            'letter' => $this->letter,
            'question' => $this->question,
            'answer' => $this->answer,
            'contributor' => $this->contributor,
            'options' => $withChoices ? $this->choices() : null,
        ], fn ($v) => $v !== null);
    }

    /** Builds the 4 options for questions saved without them. */
    public static function fillMissingOptions(): int
    {
        $count = 0;

        static::query()->whereNull('options')->get()->each(function (Question $q) use (&$count) {
            $q->options = $q->choices();
            $q->saveQuietly();
            $count++;
        });

        return $count;
    }

    public static function lettersSummary(): Collection
    {
        return static::active()->selectRaw('letter, count(*) as total')->groupBy('letter')->pluck('total', 'letter');
    }
}
