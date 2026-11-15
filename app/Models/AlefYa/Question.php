<?php

namespace App\Models\AlefYa;

use App\Support\AlefYaLetters;
use App\Support\TextKey;
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
            $q->answer_key = TextKey::make($q->answer);
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

        // 1) Bank-wide rounds: the letter's round is its lowest times_used. Serve a question of this
        //    round whose *answer* hasn't come up anywhere this round (any contest, any device), so
        //    «الصين» shows up once, and only comes back after the letter's whole bank went round.
        $round = (clone $base)->min('times_used');
        if ($round !== null) {
            $question = (clone $base)->where('times_used', $round)->whereNotIn('id', $exclude)
                ->whereNotIn('answer_key', static::active()->select('answer_key')
                    ->when($letter, fn ($q) => $q->where('letter', $letter))
                    ->where('times_used', '>', $round)->whereNotNull('answer_key'))
                ->when($exclude, fn ($q) => $q->whereNotIn('answer_key', static::query()->select('answer_key')
                    ->whereIn('id', $exclude)->whereNotNull('answer_key')))
                ->inRandomOrder()->first();

            if ($question) {
                $question->increment('times_used');

                return [$question, false];
            }
        }

        // 2) Otherwise least-played first; among those, skip answers this contest already had.
        $usedAnswers = $exclude
            ? static::query()->whereIn('id', $exclude)->pluck('answer')->map(fn ($a) => TextKey::make($a))->flip()
            : collect();
        $candidates = (clone $base)->whereNotIn('id', $exclude)->orderBy('times_used')->inRandomOrder()->limit(40)->get();
        $question = $candidates->first(fn (Question $q) => ! isset($usedAnswers[TextKey::make($q->answer)])) ?? $candidates->first();
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
