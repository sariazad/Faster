<?php

namespace App\Models;

use App\Support\TextKey;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Game extends Model
{
    /** Point levels on the board. */
    public const LEVELS = [200, 400, 600];

    /** Questions per level in every category column. */
    public const QUESTIONS_PER_LEVEL = 2;

    /** Categories each team picks. */
    public const CATEGORIES_PER_TEAM = 3;

    /** Default seconds the picking team has to answer. */
    public const MAIN_SECONDS = 60;

    /** Default seconds the other team has to steal. */
    public const STEAL_SECONDS = 30;

    /** Choices offered on the setup page. */
    public const ANSWER_OPTIONS = [20, 30, 45, 60, 90, 120];

    public const STEAL_OPTIONS = [10, 15, 20, 30, 45, 60];

    public const EXTRA_SECONDS = 30;

    public const PLAYING = 'playing';

    public const FINISHED = 'finished';

    /** Helpers: each team can use each one once per game, on its own turn. */
    public const HELPERS = [
        'double' => [
            'label' => 'دبل',
            'icon' => '×٢',
            'hint' => 'نقاط السؤال مضاعفة إذا جاوب فريقك صح',
        ],
        'extra_time' => [
            'label' => 'وقت إضافي',
            'icon' => '+٣٠',
            'hint' => '٣٠ ثانية زيادة على المؤقت',
        ],
        'no_steal' => [
            'label' => 'قفل',
            'icon' => '🔒',
            'hint' => 'الفريق الثاني ما يقدر يسرق السؤال',
        ],
    ];

    protected $fillable = [
        'uuid',
        'team_a_name',
        'team_b_name',
        'team_a_score',
        'team_b_score',
        'current_team',
        'status',
        'helpers',
        'current_game_question_id',
        'finished_at',
        'answer_seconds',
        'steal_seconds',
    ];

    protected function casts(): array
    {
        return [
            'team_a_score' => 'integer',
            'team_b_score' => 'integer',
            'helpers' => 'array',
            'answer_seconds' => 'integer',
            'steal_seconds' => 'integer',
            'finished_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'game_categories')
            ->withPivot(['team', 'position'])
            ->orderByPivot('position');
    }

    public function gameQuestions(): HasMany
    {
        return $this->hasMany(GameQuestion::class);
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(GameQuestion::class, 'current_game_question_id');
    }

    /* ---------------------------------------------------------------
     | Creating a game
     * -------------------------------------------------------------- */

    /**
     * @param  array<int>  $teamACategoryIds
     * @param  array<int>  $teamBCategoryIds
     */
    public static function start(
        string $teamA,
        string $teamB,
        array $teamACategoryIds,
        array $teamBCategoryIds,
        int $answerSeconds = self::MAIN_SECONDS,
        int $stealSeconds = self::STEAL_SECONDS,
    ): self {
        if (! in_array($answerSeconds, self::ANSWER_OPTIONS, true) || ! in_array($stealSeconds, self::STEAL_OPTIONS, true)) {
            throw new DomainException('مدة الإجابة أو السرقة غير مسموحة.');
        }

        $teamACategoryIds = array_values(array_unique(array_map('intval', $teamACategoryIds)));
        $teamBCategoryIds = array_values(array_unique(array_map('intval', $teamBCategoryIds)));

        if (count($teamACategoryIds) !== self::CATEGORIES_PER_TEAM || count($teamBCategoryIds) !== self::CATEGORIES_PER_TEAM) {
            throw new DomainException('كل فريق لازم يختار '.self::CATEGORIES_PER_TEAM.' فئات.');
        }

        if (array_intersect($teamACategoryIds, $teamBCategoryIds)) {
            throw new DomainException('ما ينفع الفريقين يختارون نفس الفئة.');
        }

        $allIds = array_merge($teamACategoryIds, $teamBCategoryIds);
        $playableIds = Category::playable()->pluck('id')->all();

        if (array_diff($allIds, $playableIds)) {
            throw new DomainException('وحدة من الفئات المختارة ما فيها أسئلة كافية.');
        }

        return DB::transaction(function () use ($teamA, $teamB, $teamACategoryIds, $teamBCategoryIds, $answerSeconds, $stealSeconds) {
            $game = self::create([
                'uuid' => (string) Str::uuid(),
                'team_a_name' => trim($teamA),
                'team_b_name' => trim($teamB),
                'current_team' => 'a',
                'status' => self::PLAYING,
                'helpers' => ['a' => [], 'b' => []],
                'answer_seconds' => $answerSeconds,
                'steal_seconds' => $stealSeconds,
            ]);

            // Board columns alternate: A1, B1, A2, B2, A3, B3
            $position = 0;
            $columns = [];
            for ($i = 0; $i < self::CATEGORIES_PER_TEAM; $i++) {
                foreach (['a' => $teamACategoryIds[$i], 'b' => $teamBCategoryIds[$i]] as $team => $categoryId) {
                    $game->categories()->attach($categoryId, ['team' => $team, 'position' => ++$position]);
                    $columns[] = $categoryId;
                }
            }

            $game->drawQuestions($columns);

            return $game;
        });
    }

    /**
     * Picks the board's questions for all columns at once.
     *
     * Rules, in order of priority:
     *  1. Never the same question twice in a game, even if its text exists in two categories.
     *  2. Rotate through the bank: least-used questions first, then the ones used longest ago,
     *     so a question only comes back after the rest of its category/level has been played.
     *  3. Avoid two questions with the same answer in one game, when there is an alternative.
     *  4. Random among equally-fresh questions, and random placement on the board.
     *
     * @param  array<int>  $categoryIds
     */
    protected function drawQuestions(array $categoryIds): void
    {
        $usedIds = [];
        $usedBodies = [];
        $usedAnswers = [];
        $picked = [];

        // Hardest levels first: they have the fewest alternatives, so they get first claim on answers.
        foreach (array_reverse(self::LEVELS) as $points) {
            foreach ($categoryIds as $categoryId) {
                $candidates = Question::query()
                    ->where('category_id', $categoryId)
                    ->where('points', $points)
                    ->where('is_active', true)
                    ->get(['id', 'body', 'image', 'audio', 'answer', 'answer_image', 'answer_audio', 'times_used', 'last_used_at'])
                    ->shuffle()
                    ->sortBy(fn (Question $q) => [$q->times_used, $q->last_used_at?->getTimestamp() ?? 0])
                    ->values();

                $chosen = $this->choose($candidates, $usedIds, $usedBodies, $usedAnswers);

                foreach ($chosen as $question) {
                    $usedIds[$question->id] = true;
                    $usedBodies[$question->identityKey()] = true;
                    if ($key = $this->answerKey($question)) {
                        $usedAnswers[$key] = true;
                    }
                }

                $picked[$categoryId][$points] = $chosen->shuffle()->values();
            }
        }

        foreach ($picked as $categoryId => $levels) {
            foreach ($levels as $points => $questions) {
                foreach ($questions as $index => $question) {
                    $this->gameQuestions()->create([
                        'question_id' => $question->id,
                        'category_id' => $categoryId,
                        'points' => $points,
                        'slot' => $index + 1,
                        'status' => GameQuestion::PENDING,
                    ]);
                }
            }
        }

        if ($usedIds) {
            Question::whereIn('id', array_keys($usedIds))->update([
                'times_used' => DB::raw('times_used + 1'),
                'last_used_at' => now(),
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Question>  $candidates  ordered freshest first
     * @return \Illuminate\Support\Collection<int, Question>
     */
    private function choose($candidates, array $usedIds, array $usedBodies, array $usedAnswers)
    {
        $chosen = collect();
        $answers = $usedAnswers;
        $bodies = $usedBodies;

        // Pass 1: fresh question and fresh answer. Pass 2: allow a repeated answer.
        // Pass 3: allow a repeated question text, so the board is always full.
        foreach ([[true, true], [false, true], [false, false]] as [$strictAnswers, $strictBodies]) {
            foreach ($candidates as $question) {
                if ($chosen->count() >= self::QUESTIONS_PER_LEVEL) {
                    break 2;
                }

                $body = $question->identityKey();
                $answer = $this->answerKey($question);

                if (isset($usedIds[$question->id]) || $chosen->contains('id', $question->id)) {
                    continue;
                }

                if ($strictBodies && isset($bodies[$body])) {
                    continue;
                }

                if ($strictAnswers && $answer && isset($answers[$answer])) {
                    continue;
                }

                $chosen->push($question);
                $bodies[$body] = true;
                if ($answer) {
                    $answers[$answer] = true;
                }
            }
        }

        return $chosen;
    }

    /** Answers too generic to count as "the same answer" (true/false rounds etc.). */
    private const GENERIC_ANSWERS = ['صح', 'غلط', 'خطا', 'نعم', 'لا'];

    private function answerKey(Question $question): string
    {
        // Picture/sound-only answers can't be compared, so they never block each other.
        $key = TextKey::make($question->answer);

        $first = strtok($key, ' ') ?: '';

        return in_array($first, self::GENERIC_ANSWERS, true) ? '' : $key;
    }

    /* ---------------------------------------------------------------
     | Playing
     * -------------------------------------------------------------- */

    public function open(GameQuestion $gameQuestion): void
    {
        $this->ensurePlaying();

        if ($gameQuestion->game_id !== $this->id) {
            throw new DomainException('هذا السؤال مو من هاللعبة.');
        }

        if (! $gameQuestion->isPending()) {
            throw new DomainException('هذا السؤال انلعب قبل.');
        }

        if ($this->current_game_question_id && $this->current_game_question_id !== $gameQuestion->id) {
            throw new DomainException('فيه سؤال مفتوح، خلّصوه أول.');
        }

        $gameQuestion->update(['opened_by' => $this->current_team, 'effects' => []]);
        $this->update(['current_game_question_id' => $gameQuestion->id]);
        $this->setRelation('currentQuestion', $gameQuestion);
    }

    /**
     * Returns the current question to the board without scoring (host mistake).
     * Helpers already used stay used.
     */
    public function cancelCurrent(): void
    {
        $current = $this->currentQuestion;

        if ($current) {
            $current->update(['opened_by' => null, 'effects' => []]);
        }

        $this->update(['current_game_question_id' => null]);
        $this->unsetRelation('currentQuestion');
    }

    public function useHelper(string $helper): void
    {
        $this->ensurePlaying();

        if (! array_key_exists($helper, self::HELPERS)) {
            throw new DomainException('وسيلة مساعدة غير معروفة.');
        }

        $current = $this->currentQuestion;

        if (! $current) {
            throw new DomainException('الوسائل تُستخدم أثناء السؤال فقط.');
        }

        $team = $current->opened_by;

        if (! $this->helperAvailable($team, $helper)) {
            throw new DomainException('هالوسيلة مستخدمة قبل.');
        }

        $helpers = $this->helpers ?? ['a' => [], 'b' => []];
        $helpers[$team][] = $helper;
        $this->update(['helpers' => $helpers]);

        $current->update(['effects' => array_values(array_unique([...($current->effects ?? []), $helper]))]);
    }

    public function helperAvailable(string $team, string $helper): bool
    {
        return ! in_array($helper, $this->helpers[$team] ?? [], true);
    }

    /**
     * Scores the current question.
     *
     * @param  string  $team  'a', 'b', or 'n' for nobody
     */
    public function award(string $team): int
    {
        $this->ensurePlaying();

        if (! in_array($team, ['a', 'b', 'n'], true)) {
            throw new DomainException('فريق غير معروف.');
        }

        $current = $this->currentQuestion;

        if (! $current) {
            throw new DomainException('ما فيه سؤال مفتوح.');
        }

        $opener = $current->opened_by;

        if ($team !== 'n' && $team !== $opener && $current->hasEffect('no_steal')) {
            throw new DomainException('السؤال مقفول، الفريق الثاني ما يقدر يسرقه.');
        }

        $points = 0;
        if ($team !== 'n') {
            $points = $current->points;
            if ($team === $opener && $current->hasEffect('double')) {
                $points *= 2;
            }
        }

        DB::transaction(function () use ($current, $team, $points, $opener) {
            $current->update([
                'status' => GameQuestion::ANSWERED,
                'answered_by' => $team,
                'awarded_points' => $points,
            ]);

            if ($points > 0) {
                $this->increment("team_{$team}_score", $points);
            }

            $this->current_team = self::other($opener);
            $this->current_game_question_id = null;

            if (! $this->gameQuestions()->where('status', GameQuestion::PENDING)->exists()) {
                $this->status = self::FINISHED;
                $this->finished_at = now();
            }

            $this->save();
        });

        $this->unsetRelation('currentQuestion');

        return $points;
    }

    /** Host override: hand the turn to the other team (only between questions). */
    public function switchTurn(): void
    {
        $this->ensurePlaying();

        if ($this->current_game_question_id) {
            throw new DomainException('فيه سؤال مفتوح، خلّصوه أول.');
        }

        $this->update(['current_team' => self::other($this->current_team)]);
    }

    public function adjustScore(string $team, int $delta): void
    {
        if (! in_array($team, ['a', 'b'], true)) {
            throw new DomainException('فريق غير معروف.');
        }

        $this->increment("team_{$team}_score", $delta);
    }

    public function finish(): void
    {
        $this->cancelCurrent();
        $this->update(['status' => self::FINISHED, 'finished_at' => now()]);
    }

    /* ---------------------------------------------------------------
     | Helpers
     * -------------------------------------------------------------- */

    public static function other(string $team): string
    {
        return $team === 'a' ? 'b' : 'a';
    }

    public function teamName(string $team): string
    {
        return $team === 'a' ? $this->team_a_name : $this->team_b_name;
    }

    public function score(string $team): int
    {
        return $team === 'a' ? $this->team_a_score : $this->team_b_score;
    }

    public function isFinished(): bool
    {
        return $this->status === self::FINISHED;
    }

    /** 'a', 'b' or 'tie'. */
    public function winner(): string
    {
        return match (true) {
            $this->team_a_score > $this->team_b_score => 'a',
            $this->team_b_score > $this->team_a_score => 'b',
            default => 'tie',
        };
    }

    protected function ensurePlaying(): void
    {
        if ($this->isFinished()) {
            throw new DomainException('اللعبة خلصت.');
        }
    }
}
