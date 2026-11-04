<?php

namespace App\Services;

use App\Models\BoardCell;
use App\Models\Category;
use App\Models\CellQuestion;
use App\Models\Game;
use App\Models\Question;
use App\Support\TextKey;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * «طق طق» — من طق طق للسلام عليكم.
 *
 * An N×N hex board (pointy-top hexes, odd rows shifted half a cell).
 * Team A connects TOP ↔ BOTTOM, team B connects RIGHT ↔ LEFT.
 * A filled hex board always has exactly one winner, so a game can't end in a draw.
 *
 * Turn: the team in turn picks a free cell → a question from that cell's category.
 * Right answer → the cell is theirs. Wrong → the other team may steal it.
 * Then the turn passes to the other team.
 */
class Taqtaq
{
    public const SIZES = [4, 5, 6];

    public const DEFAULT_SIZE = 5;

    public const CATEGORIES_PER_TEAM = 2;

    /** A category needs at least this many active questions to be offered. */
    public const MIN_QUESTIONS = 6;

    public const HELPERS = [
        'change' => [
            'label' => 'غيّره',
            'icon' => '🔄',
            'hint' => 'يبدّل سؤال الخصم بسؤال جديد قبل ما يجاوب',
        ],
        'erase' => [
            'label' => 'امسحها',
            'icon' => '🧽',
            'hint' => 'يرجّع خلية من خلايا الخصم فاضية',
        ],
        'protect' => [
            'label' => 'احمِها',
            'icon' => '🛡️',
            'hint' => 'يقفل خلية من خلاياك فما حدا يقدر يمسحها',
        ],
    ];

    /* ---------------------------------------------------------------
     | Setup
     * -------------------------------------------------------------- */

    /** Active categories with enough questions for a board. */
    public static function playableCategories(): Collection
    {
        return Category::query()
            ->active()
            ->withCount(['questions as active_questions_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $c) => $c->active_questions_count >= self::MIN_QUESTIONS)
            ->values();
    }

    /**
     * @param  array<int>  $teamACategoryIds
     * @param  array<int>  $teamBCategoryIds
     */
    public function start(
        string $teamA,
        string $teamB,
        array $teamACategoryIds,
        array $teamBCategoryIds,
        int $size = self::DEFAULT_SIZE,
        int $answerSeconds = Game::MAIN_SECONDS,
        int $stealSeconds = Game::STEAL_SECONDS,
    ): Game {
        $a = array_values(array_unique(array_map('intval', $teamACategoryIds)));
        $b = array_values(array_unique(array_map('intval', $teamBCategoryIds)));

        if (count($a) !== self::CATEGORIES_PER_TEAM || count($b) !== self::CATEGORIES_PER_TEAM) {
            throw new DomainException('كل فريق لازم يختار '.self::CATEGORIES_PER_TEAM.' فئات.');
        }

        if (array_intersect($a, $b)) {
            throw new DomainException('ما ينفع الفريقين يختارون نفس الفئة.');
        }

        if (! in_array($size, self::SIZES, true)) {
            throw new DomainException('حجم اللوحة غير مسموح.');
        }

        if (! in_array($answerSeconds, Game::ANSWER_OPTIONS, true) || ! in_array($stealSeconds, Game::STEAL_OPTIONS, true)) {
            throw new DomainException('مدة الإجابة أو السرقة غير مسموحة.');
        }

        $playable = self::playableCategories()->pluck('id')->all();
        if (array_diff([...$a, ...$b], $playable)) {
            throw new DomainException('وحدة من الفئات المختارة ما فيها أسئلة كافية.');
        }

        return DB::transaction(function () use ($teamA, $teamB, $a, $b, $size, $answerSeconds, $stealSeconds) {
            $game = Game::create([
                'uuid' => (string) Str::uuid(),
                'mode' => Game::TAQTAQ,
                'board_size' => $size,
                'team_a_name' => trim($teamA),
                'team_b_name' => trim($teamB),
                'current_team' => 'a',
                'status' => Game::PLAYING,
                'helpers' => ['a' => [], 'b' => []],
                'answer_seconds' => $answerSeconds,
                'steal_seconds' => $stealSeconds,
            ]);

            $position = 0;
            foreach ([$a, $b] as $team => $ids) {
                foreach ($ids as $categoryId) {
                    $game->categories()->attach($categoryId, ['team' => $team === 0 ? 'a' : 'b', 'position' => ++$position]);
                }
            }

            // Spread the 4 categories evenly over the board, in random places.
            $categories = [...$a, ...$b];
            $total = $size * $size;
            $deck = collect(range(0, $total - 1))
                ->map(fn ($i) => $categories[$i % count($categories)])
                ->shuffle()
                ->values();

            $now = now();
            $rows = [];
            for ($row = 0; $row < $size; $row++) {
                for ($col = 0; $col < $size; $col++) {
                    $rows[] = [
                        'game_id' => $game->id,
                        'row' => $row,
                        'col' => $col,
                        'category_id' => $deck[$row * $size + $col],
                        'owner' => null,
                        'protected' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            BoardCell::insert($rows);

            return $game;
        });
    }

    /* ---------------------------------------------------------------
     | Playing
     * -------------------------------------------------------------- */

    public function open(Game $game, BoardCell $cell): CellQuestion
    {
        $this->ensurePlaying($game);
        $this->ensureCell($game, $cell);

        if ($game->current_cell_question_id) {
            throw new DomainException('فيه سؤال مفتوح، خلّصوه أول.');
        }

        if (! $cell->isFree()) {
            throw new DomainException('هالخلية مأخوذة.');
        }

        return DB::transaction(function () use ($game, $cell) {
            $question = $this->drawQuestion($game, $cell->category_id);

            $current = $game->cellQuestions()->create([
                'board_cell_id' => $cell->id,
                'question_id' => $question->id,
                'opened_by' => $game->current_team,
            ]);

            $game->update(['current_cell_question_id' => $current->id]);

            return $current;
        });
    }

    /**
     * @param  string  $team  'a', 'b' or 'n' (nobody)
     */
    public function award(Game $game, string $team): ?array
    {
        $this->ensurePlaying($game);

        if (! in_array($team, ['a', 'b', 'n'], true)) {
            throw new DomainException('فريق غير معروف.');
        }

        $current = $this->current($game);
        $cell = $current->cell;

        return DB::transaction(function () use ($game, $current, $cell, $team) {
            $current->update(['answered_by' => $team]);

            if ($team !== 'n') {
                $cell->update(['owner' => $team]);
            }

            $game->current_team = Game::other($current->opened_by);
            $game->current_cell_question_id = null;

            $path = $team !== 'n' ? $this->winningPath($game, $team) : null;

            if ($path) {
                $game->status = Game::FINISHED;
                $game->finished_at = now();
                $game->winner = $team;
                $game->winning_path = $path;
            }

            $game->save();
            $game->unsetRelation('currentCellQuestion');

            return $path;
        });
    }

    /** Back to the board without anyone taking the cell (host mistake). */
    public function cancel(Game $game): void
    {
        if ($game->current_cell_question_id) {
            CellQuestion::whereKey($game->current_cell_question_id)->update(['answered_by' => 'x']);
        }

        $game->update(['current_cell_question_id' => null]);
        $game->unsetRelation('currentCellQuestion');
    }

    /** «غيّره»: the team that is NOT answering swaps the open question. */
    public function change(Game $game): CellQuestion
    {
        $this->ensurePlaying($game);
        $current = $this->current($game);
        $team = Game::other($current->opened_by);

        $this->useHelper($game, $team, 'change');

        return DB::transaction(function () use ($game, $current) {
            $current->update(['answered_by' => 'x']);

            $question = $this->drawQuestion($game, $current->cell->category_id);

            $next = $game->cellQuestions()->create([
                'board_cell_id' => $current->board_cell_id,
                'question_id' => $question->id,
                'opened_by' => $current->opened_by,
            ]);

            $game->update(['current_cell_question_id' => $next->id]);
            $game->unsetRelation('currentCellQuestion');

            return $next;
        });
    }

    /** «امسحها»: the team in turn frees one of the opponent's cells. */
    public function erase(Game $game, BoardCell $cell): void
    {
        $this->ensurePlaying($game);
        $this->ensureCell($game, $cell);
        $this->ensureBetweenQuestions($game);

        $team = $game->current_team;

        if ($cell->owner !== Game::other($team)) {
            throw new DomainException('امسحها تشتغل على خلايا الخصم بس.');
        }

        if ($cell->protected) {
            throw new DomainException('هالخلية محمية 🛡️');
        }

        $this->useHelper($game, $team, 'erase');
        $cell->update(['owner' => null]);
    }

    /** «احمِها»: the team in turn locks one of its own cells against «امسحها». */
    public function protect(Game $game, BoardCell $cell): void
    {
        $this->ensurePlaying($game);
        $this->ensureCell($game, $cell);
        $this->ensureBetweenQuestions($game);

        $team = $game->current_team;

        if ($cell->owner !== $team) {
            throw new DomainException('احمِها تشتغل على خلايا فريقك بس.');
        }

        if ($cell->protected) {
            throw new DomainException('هالخلية محمية أصلاً.');
        }

        $this->useHelper($game, $team, 'protect');
        $cell->update(['protected' => true]);
    }

    public function switchTurn(Game $game): void
    {
        $this->ensurePlaying($game);
        $this->ensureBetweenQuestions($game);

        $game->update(['current_team' => Game::other($game->current_team)]);
    }

    public function finish(Game $game): void
    {
        $this->cancel($game);
        $game->update(['status' => Game::FINISHED, 'finished_at' => now()]);
    }

    public function helperAvailable(Game $game, string $team, string $helper): bool
    {
        return ! in_array($helper, $game->helpers[$team] ?? [], true);
    }

    public function cellCount(Game $game, string $team): int
    {
        return $game->cells()->where('owner', $team)->count();
    }

    /* ---------------------------------------------------------------
     | Board geometry
     * -------------------------------------------------------------- */

    /**
     * Neighbours on an "odd-r" offset grid: odd rows are shifted half a cell to the right.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public static function neighbours(int $row, int $col, int $size): array
    {
        $shift = $row % 2 === 1 ? 1 : 0;

        $candidates = [
            [$row, $col - 1], [$row, $col + 1],
            [$row - 1, $col - 1 + $shift], [$row - 1, $col + $shift],
            [$row + 1, $col - 1 + $shift], [$row + 1, $col + $shift],
        ];

        return array_values(array_filter(
            $candidates,
            fn ($p) => $p[0] >= 0 && $p[0] < $size && $p[1] >= 0 && $p[1] < $size
        ));
    }

    /**
     * A shortest chain of the team's cells between its two sides, or null.
     * Team A: row 0 → row N-1. Team B: col 0 → col N-1.
     *
     * @return array<int>|null board_cell ids along the path
     */
    public function winningPath(Game $game, string $team): ?array
    {
        $size = (int) $game->board_size;
        $grid = [];

        foreach ($game->cells()->get(['id', 'row', 'col', 'owner']) as $cell) {
            if ($cell->owner === $team) {
                $grid[$cell->row][$cell->col] = $cell->id;
            }
        }

        $isStart = fn ($r, $c) => $team === 'a' ? $r === 0 : $c === 0;
        $isEnd = fn ($r, $c) => $team === 'a' ? $r === $size - 1 : $c === $size - 1;

        $queue = [];
        $parent = [];

        foreach ($grid as $r => $cols) {
            foreach ($cols as $c => $id) {
                if ($isStart($r, $c)) {
                    $queue[] = [$r, $c];
                    $parent["$r,$c"] = null;
                }
            }
        }

        while ($queue) {
            [$r, $c] = array_shift($queue);

            if ($isEnd($r, $c)) {
                $path = [];
                for ($key = "$r,$c"; $key !== null; $key = $parent[$key]) {
                    [$pr, $pc] = array_map('intval', explode(',', $key));
                    $path[] = $grid[$pr][$pc];
                }

                return array_reverse($path);
            }

            foreach (self::neighbours($r, $c, $size) as [$nr, $nc]) {
                if (isset($grid[$nr][$nc]) && ! array_key_exists("$nr,$nc", $parent)) {
                    $parent["$nr,$nc"] = "$r,$c";
                    $queue[] = [$nr, $nc];
                }
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------
     | Question selection
     * -------------------------------------------------------------- */

    /**
     * Least-used question of the category that this game hasn't shown yet,
     * avoiding a repeated text/picture or a repeated answer when possible.
     */
    private function drawQuestion(Game $game, int $categoryId): Question
    {
        $shown = Question::query()
            ->whereIn('id', $game->cellQuestions()->select('question_id'))
            ->get(['id', 'body', 'image', 'audio', 'answer']);

        $shownIds = $shown->pluck('id')->flip();
        $shownKeys = $shown->map(fn (Question $q) => $q->identityKey())->flip();
        $shownAnswers = $shown->map(fn (Question $q) => TextKey::make($q->answer))->filter()->flip();

        $candidates = Question::query()
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->get()
            ->shuffle()
            ->sortBy(fn (Question $q) => [$q->times_used, $q->last_used_at?->getTimestamp() ?? 0])
            ->values();

        $pick = $candidates->first(fn (Question $q) => ! isset($shownIds[$q->id])
                && ! isset($shownKeys[$q->identityKey()])
                && ! isset($shownAnswers[TextKey::make($q->answer)]))
            ?? $candidates->first(fn (Question $q) => ! isset($shownIds[$q->id]) && ! isset($shownKeys[$q->identityKey()]))
            ?? $candidates->first(fn (Question $q) => ! isset($shownIds[$q->id]))
            ?? $candidates->first();

        if (! $pick) {
            throw new DomainException('ما فيه أسئلة مفعّلة بهالفئة.');
        }

        Question::whereKey($pick->id)->update([
            'times_used' => DB::raw('times_used + 1'),
            'last_used_at' => now(),
        ]);

        return $pick;
    }

    /* ---------------------------------------------------------------
     | Guards
     * -------------------------------------------------------------- */

    private function current(Game $game): CellQuestion
    {
        $current = $game->current_cell_question_id
            ? CellQuestion::with('cell')->find($game->current_cell_question_id)
            : null;

        if (! $current) {
            throw new DomainException('ما فيه سؤال مفتوح.');
        }

        return $current;
    }

    private function useHelper(Game $game, string $team, string $helper): void
    {
        if (! $this->helperAvailable($game, $team, $helper)) {
            throw new DomainException('هالوسيلة مستخدمة قبل.');
        }

        $helpers = $game->helpers ?? ['a' => [], 'b' => []];
        $helpers[$team][] = $helper;
        $game->update(['helpers' => $helpers]);
    }

    private function ensurePlaying(Game $game): void
    {
        if ($game->isFinished()) {
            throw new DomainException('اللعبة خلصت.');
        }
    }

    private function ensureBetweenQuestions(Game $game): void
    {
        if ($game->current_cell_question_id) {
            throw new DomainException('خلّصوا السؤال المفتوح أول.');
        }
    }

    private function ensureCell(Game $game, BoardCell $cell): void
    {
        if ($cell->game_id !== $game->id) {
            throw new DomainException('هالخلية مو من هاللعبة.');
        }
    }
}
