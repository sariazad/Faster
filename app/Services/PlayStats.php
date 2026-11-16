<?php

namespace App\Services;

use App\Models\Akher\GameList;
use App\Models\AlefYa\Score;
use App\Models\Game;
use App\Models\PlayLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Statistics for every game, read from where each game keeps its history:
 *   مين الأسبق / طق طق → games table, ألف ياء challenge → alefya scores,
 *   ألف ياء teams + آخر القائمة + قدّها؟ → play_logs.
 * Every session becomes one normalized row (see row()).
 */
class PlayStats
{
    public const TZ = 'Asia/Riyadh';

    /** A session with no activity for this long counts as left unfinished. */
    public const IDLE_MINUTES = 120;

    /** Sessions left open for hours would skew the averages. */
    public const MAX_SECONDS = 4 * 3600;

    public const GAMES = [
        'classic' => ['label' => 'مين الأسبق', 'icon' => '🎯', 'color' => '#f59e0b'],
        'taqtaq' => ['label' => 'طق طق', 'icon' => '⬢', 'color' => '#10b981'],
        'alefya' => ['label' => 'ألف ياء (فرق)', 'icon' => '🔤', 'color' => '#8b5cf6'],
        'alefya_solo' => ['label' => 'ألف ياء (التحدي)', 'icon' => '⏱️', 'color' => '#ec4899'],
        'akher' => ['label' => 'آخر القائمة', 'icon' => '📋', 'color' => '#3b82f6'],
        'qaddaha' => ['label' => 'قدّها؟', 'icon' => '🔨', 'color' => '#f43f5e'],
    ];

    public const PERIODS = [
        'today' => 'اليوم',
        '7d' => 'آخر ٧ أيام',
        '30d' => 'آخر ٣٠ يوم',
        '90d' => 'آخر ٣ شهور',
        'all' => 'من البداية',
    ];

    private ?Collection $rows = null;

    public function __construct(public string $period = '30d')
    {
        if (! isset(self::PERIODS[$this->period])) {
            $this->period = '30d';
        }
    }

    /** Start of the period (UTC), null = everything. */
    public function since(): ?Carbon
    {
        $now = now(self::TZ);

        $start = match ($this->period) {
            'today' => $now->copy()->startOfDay(),
            '7d' => $now->copy()->subDays(6)->startOfDay(),
            '30d' => $now->copy()->subDays(29)->startOfDay(),
            '90d' => $now->copy()->subDays(89)->startOfDay(),
            default => null,
        };

        return $start?->utc();
    }

    /** @return Collection<int, array> every session in the period, newest first */
    public function sessions(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $since = $this->since();
        $rows = collect();

        // مين الأسبق + طق طق
        Game::query()
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->withCount([
                'gameQuestions as played_q' => fn ($q) => $q->where('status', 'answered'),
                'cellQuestions as played_c' => fn ($q) => $q->where(fn ($w) => $w->whereNull('answered_by')->orWhere('answered_by', '!=', 'x')),
            ])
            ->get()
            ->each(function (Game $g) use ($rows) {
                $taqtaq = $g->mode === Game::TAQTAQ;
                $finished = $g->status === Game::FINISHED;
                $winner = $taqtaq
                    ? ($g->winner === 'a' ? $g->team_a_name : ($g->winner === 'b' ? $g->team_b_name : null))
                    : ($g->team_a_score === $g->team_b_score ? 'تعادل' : ($g->team_a_score > $g->team_b_score ? $g->team_a_name : $g->team_b_name));

                $rows->push($this->row(
                    $taqtaq ? 'taqtaq' : 'classic',
                    $g->created_at,
                    $finished ? ($g->finished_at ?? $g->updated_at) : $g->updated_at,
                    $finished,
                    players: 2,
                    questions: $taqtaq ? $g->played_c : $g->played_q,
                    winner: $finished ? $winner : null,
                    detail: $g->team_a_name.' × '.$g->team_b_name.($taqtaq && $g->board_size ? " · {$g->board_size}×{$g->board_size}" : ''),
                ));
            });

        // ألف ياء: the challenge (one row per finished attempt; the timer decides its length)
        $seconds = (int) config('alefya.solo.seconds', 30);
        $bonus = (int) config('alefya.solo.bonus', 2);
        $cells = (int) config('alefya.solo.rows', 3) * (int) config('alefya.solo.cols', 5);
        Score::query()
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->get()
            ->each(function (Score $s) use ($rows, $seconds, $bonus, $cells) {
                $length = max(1, $seconds + $bonus * $s->correct - $s->time_left); // the timer plus each correct answer's bonus
                $rows->push($this->row(
                    'alefya_solo',
                    $s->created_at->copy()->subSeconds($length),
                    $s->created_at,
                    true,
                    players: 1,
                    questions: $s->correct + $s->wrong,
                    winner: $s->won ? 'فاز' : 'خسر',
                    detail: "{$s->correct} صح من {$cells}",
                ));
            });

        // ألف ياء teams + آخر القائمة
        PlayLog::query()
            ->when($since, fn ($q) => $q->where('started_at', '>=', $since))
            ->get()
            ->each(function (PlayLog $log) use ($rows) {
                $meta = $log->meta ?? [];

                if ($log->game === PlayLog::AKHER) {
                    $lists = $meta['played'] ?? $meta['lists'] ?? [];
                    $detail = ($meta['mode'] ?? 'teams') === 'solo' ? 'فردي' : 'فرق';
                    $detail .= ' · '.count($lists).' '.(count($lists) === 1 ? 'قائمة' : 'قوائم');
                    $questions = $meta['guesses'] ?? null;
                } elseif ($log->game === 'qaddaha') {
                    $names = array_filter($meta['names'] ?? []);
                    $kinds = ['full' => 'كاملة', 'quick' => 'سريعة', 'duel' => 'مواجهة'];
                    $detail = ($names ? implode(' × ', $names) : 'فريقين').' · '.($kinds[$meta['kind'] ?? ''] ?? 'لعبة')
                        .(($meta['mode'] ?? '') === 'multi' ? ' · جوالات' : '');
                    $questions = ($meta['played'] ?? 0) ?: null;
                } else {
                    $names = array_filter($meta['names'] ?? []);
                    $detail = $names ? implode(' × ', $names) : 'فريقين';
                    $detail .= ! empty($meta['size']) ? " · {$meta['size']}×{$meta['size']}" : '';
                    $questions = array_sum($meta['correct'] ?? []) ?: null;
                }

                $rows->push($this->row(
                    $log->game,
                    $log->started_at,
                    $log->finished ? ($log->ended_at ?? $log->last_at) : ($log->last_at ?? $log->started_at),
                    $log->finished,
                    players: $log->players,
                    questions: $questions,
                    winner: $log->winner,
                    detail: $detail,
                    rounds: $log->rounds,
                    lists: $log->game === PlayLog::AKHER ? ($meta['played'] ?? $meta['lists'] ?? []) : [],
                ));
            });

        return $this->rows = $rows->sortByDesc(fn ($r) => $r['start']->timestamp)->values();
    }

    private function row(string $game, Carbon $start, ?Carbon $last, bool $finished, ?int $players = null, ?int $questions = null, ?string $winner = null, string $detail = '', ?int $rounds = null, array $lists = []): array
    {
        $last ??= $start;
        $seconds = min(self::MAX_SECONDS, max(0, $last->timestamp - $start->timestamp));

        return [
            'game' => $game,
            'start' => $start,
            'seconds' => $seconds,
            'finished' => $finished,
            'state' => $finished ? 'finished' : ($last->lt(now()->subMinutes(self::IDLE_MINUTES)) ? 'abandoned' : 'live'),
            'players' => $players,
            'questions' => $questions,
            'winner' => $winner,
            'detail' => $detail,
            'rounds' => $rounds,
            'lists' => $lists,
        ];
    }

    /** Headline numbers for the whole period. */
    public function totals(): array
    {
        $rows = $this->sessions();
        $finished = $rows->where('finished', true);

        return [
            'sessions' => $rows->count(),
            'finished' => $finished->count(),
            'seconds' => $rows->sum('seconds'),
            'avg' => (int) round($finished->avg('seconds') ?? 0),
            'questions' => $rows->sum(fn ($r) => $r['questions'] ?? 0),
            'live' => $rows->where('state', 'live')->count(),
            'days' => $rows->map(fn ($r) => $r['start']->copy()->setTimezone(self::TZ)->toDateString())->unique()->count(),
        ];
    }

    /** One card per game. */
    public function perGame(): array
    {
        $rows = $this->sessions();
        $out = [];

        foreach (self::GAMES as $key => $info) {
            $mine = $rows->where('game', $key);
            $finished = $mine->where('finished', true);
            $last = $mine->first();

            $out[$key] = $info + [
                'sessions' => $mine->count(),
                'finished' => $finished->count(),
                'abandoned' => $mine->where('state', 'abandoned')->count(),
                'seconds' => $mine->sum('seconds'),
                'avg' => (int) round($finished->avg('seconds') ?? 0),
                'longest' => (int) ($finished->max('seconds') ?? 0),
                'questions' => $mine->sum(fn ($r) => $r['questions'] ?? 0),
                'players' => round($mine->whereNotNull('players')->avg('players') ?? 0, 1),
                'wins' => $key === 'alefya_solo' ? $mine->where('winner', 'فاز')->count() : null,
                'last' => $last ? $last['start'] : null,
                'share' => $rows->count() ? round($mine->count() * 100 / $rows->count()) : 0,
            ];
        }

        return $out;
    }

    /** Sessions per time bucket and game (stacked chart). */
    public function timeline(): array
    {
        [$unit, $count] = match ($this->period) {
            'today' => ['hour', 24],
            '7d' => ['day', 7],
            '30d' => ['day', 30],
            '90d' => ['week', 13],
            default => ['month', null],
        };

        $now = now(self::TZ);
        $bucket = fn (Carbon $t) => match ($unit) {
            'hour' => $t->copy()->startOfHour(),
            'day' => $t->copy()->startOfDay(),
            'week' => $t->copy()->startOfWeek(Carbon::SATURDAY),
            default => $t->copy()->startOfMonth(),
        };

        $rows = $this->sessions()->map(fn ($r) => $r + ['b' => $bucket($r['start']->copy()->setTimezone(self::TZ))->format('Y-m-d H')]);

        if ($count === null) {
            $first = $rows->min(fn ($r) => $r['start']->timestamp);
            $from = $first ? Carbon::createFromTimestamp($first, self::TZ)->startOfMonth() : $now->copy()->startOfMonth();
            $count = max(1, (int) $from->diffInMonths($now->copy()->startOfMonth()) + 1);
        } else {
            $from = match ($unit) {
                'hour' => $now->copy()->startOfDay(),
                'day' => $now->copy()->subDays($count - 1)->startOfDay(),
                default => $now->copy()->startOfWeek(Carbon::SATURDAY)->subWeeks($count - 1),
            };
        }

        $keys = [];
        $labels = [];
        for ($i = 0; $i < $count; $i++) {
            $t = match ($unit) {
                'hour' => $from->copy()->addHours($i),
                'day' => $from->copy()->addDays($i),
                'week' => $from->copy()->addWeeks($i),
                default => $from->copy()->addMonths($i),
            };
            $keys[] = $t->format('Y-m-d H');
            $labels[] = match ($unit) {
                'hour' => self::hourLabel($t->hour),
                'day' => $count === 7 ? self::weekday($t).' '.$t->format('d/m') : $t->format('d/m'),
                'week' => $t->format('d/m'),
                default => $t->format('m/Y'),
            };
        }

        $datasets = [];
        foreach (self::GAMES as $key => $info) {
            $counts = $rows->where('game', $key)->countBy('b');
            $data = array_map(fn ($k) => $counts[$k] ?? 0, $keys);
            if (array_sum($data) > 0) {
                $datasets[] = ['label' => $info['label'], 'data' => $data, 'backgroundColor' => $info['color'], 'borderRadius' => 4];
            }
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }

    /** When people play: sessions per hour of the day (Riyadh time). */
    public function hours(): array
    {
        $counts = $this->sessions()->countBy(fn ($r) => (int) $r['start']->copy()->setTimezone(self::TZ)->format('G'));
        $labels = [];
        $data = [];
        for ($h = 0; $h < 24; $h++) {
            $labels[] = self::hourLabel($h);
            $data[] = $counts[$h] ?? 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /** Sessions per weekday (Saturday first). */
    public function weekdays(): array
    {
        $counts = $this->sessions()->countBy(fn ($r) => $r['start']->copy()->setTimezone(self::TZ)->dayOfWeek);
        $order = [6, 0, 1, 2, 3, 4, 5];

        return [
            'labels' => array_map(fn ($d) => self::DAYS[$d], $order),
            'data' => array_map(fn ($d) => $counts[$d] ?? 0, $order),
        ];
    }

    /** Busiest hour and day, for the headline. */
    public function peaks(): array
    {
        $hours = $this->hours()['data'];
        $days = $this->weekdays();
        $h = array_sum($hours) ? array_keys($hours, max($hours))[0] : null;
        $d = array_sum($days['data']) ? array_keys($days['data'], max($days['data']))[0] : null;

        return [
            'hour' => $h === null ? null : self::hourLabel($h),
            'day' => $d === null ? null : $days['labels'][$d],
        ];
    }

    /** مين الأسبق + طق طق: categories with the most questions played. */
    public function topCategories(int $limit = 10): Collection
    {
        $since = $this->since();

        $classic = DB::table('game_questions')
            ->join('categories', 'categories.id', '=', 'game_questions.category_id')
            ->where('game_questions.status', 'answered')
            ->when($since, fn ($q) => $q->where('game_questions.updated_at', '>=', $since))
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.id, categories.name, count(*) as n, count(distinct game_questions.game_id) as games')
            ->get();

        $taqtaq = DB::table('cell_questions')
            ->join('board_cells', 'board_cells.id', '=', 'cell_questions.board_cell_id')
            ->join('categories', 'categories.id', '=', 'board_cells.category_id')
            ->where(fn ($w) => $w->whereNull('cell_questions.answered_by')->orWhere('cell_questions.answered_by', '!=', 'x'))
            ->when($since, fn ($q) => $q->where('cell_questions.created_at', '>=', $since))
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.id, categories.name, count(*) as n, count(distinct cell_questions.game_id) as games')
            ->get();

        return $classic->concat($taqtaq)
            ->groupBy('id')
            ->map(fn ($g) => ['name' => $g->first()->name, 'questions' => $g->sum('n'), 'games' => $g->sum('games')])
            ->sortByDesc('questions')
            ->take($limit)
            ->values();
    }

    /** آخر القائمة: the lists played most. */
    public function topLists(int $limit = 10): Collection
    {
        $counts = $this->sessions()->where('game', 'akher')->flatMap(fn ($r) => $r['lists'])->filter()->countBy();
        $titles = $counts->isEmpty() ? collect() : GameList::whereKey($counts->keys()->all())->pluck('title_ar', 'id');

        return $counts->sortDesc()->take($limit)->map(fn ($n, $id) => ['name' => $titles[$id] ?? $id, 'plays' => $n])->values();
    }

    /** Latest sessions of every game. */
    public function recent(int $limit = 15): Collection
    {
        return $this->sessions()->take($limit);
    }

    public const DAYS = [0 => 'الأحد', 1 => 'الإثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'];

    public static function weekday(Carbon $t): string
    {
        return self::DAYS[$t->dayOfWeek];
    }

    public static function hourLabel(int $h): string
    {
        $h12 = $h % 12 ?: 12;

        return $h12.($h < 12 ? ' ص' : ' م');
    }

    /** 3725 → «١ س ٢ د» style, kept in Latin digits like the rest of the panel. */
    public static function duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        if ($h) {
            return $h.' س'.($m ? ' '.$m.' د' : '');
        }
        if ($m) {
            return $m.' د'.($m < 10 && $s ? ' '.$s.' ث' : '');
        }

        return $s.' ث';
    }
}
