<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameQuestion;
use App\Models\Question;
use App\Support\TextKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Removes duplicate questions from the database.
 *
 * Two questions are duplicates when their text is the same after ignoring
 * harakat, hamza forms, punctuation and extra spaces (see TextKey).
 *
 *  - Same category: the extra copies are always removed.
 *  - Different categories: the extra copy is removed only if its category/level
 *    keeps enough questions to stay playable; otherwise it stays (the game
 *    already never puts both on one board).
 *
 * The kept copy is the one with the most media, then the oldest. Past games
 * that used a removed copy are re-pointed to the kept one, so no history is lost.
 */
class QuestionDeduplicator
{
    /** A category level needs at least this many active questions to stay healthy. */
    private const MIN_PER_LEVEL = Game::QUESTIONS_PER_LEVEL * 2;

    /**
     * @return array{groups: int, removed: int, kept_for_playability: int, examples: array<int, string>}
     */
    public function run(bool $dryRun = false): array
    {
        $report = ['groups' => 0, 'removed' => 0, 'kept_for_playability' => 0, 'examples' => []];

        $groups = Question::query()
            ->get(['id', 'category_id', 'points', 'body', 'image', 'audio', 'answer_image', 'answer_audio', 'is_active', 'times_used'])
            ->groupBy(fn (Question $q) => TextKey::make($q->body))
            ->filter(fn (Collection $group, string $key) => $key !== '' && $group->count() > 1);

        // Active questions per category+level, kept up to date as we remove.
        $levelCounts = Question::query()
            ->where('is_active', true)
            ->selectRaw('category_id, points, count(*) as total')
            ->groupBy('category_id', 'points')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->category_id}-{$row->points}" => (int) $row->total])
            ->all();

        DB::transaction(function () use ($groups, $dryRun, &$report, &$levelCounts) {
            foreach ($groups as $group) {
                $report['groups']++;

                $keeper = $group->sortBy([
                    fn ($a, $b) => $this->mediaCount($b) <=> $this->mediaCount($a),
                    fn ($a, $b) => $b->is_active <=> $a->is_active,
                    fn ($a, $b) => $a->id <=> $b->id,
                ])->first();

                foreach ($group as $copy) {
                    if ($copy->id === $keeper->id) {
                        continue;
                    }

                    $levelKey = "{$copy->category_id}-{$copy->points}";
                    $sameCategory = $copy->category_id === $keeper->category_id;

                    if (! $sameCategory && $copy->is_active && ($levelCounts[$levelKey] ?? 0) <= self::MIN_PER_LEVEL) {
                        $report['kept_for_playability']++;

                        continue;
                    }

                    $report['removed']++;
                    if (count($report['examples']) < 8) {
                        $report['examples'][] = $copy->body;
                    }

                    if ($copy->is_active) {
                        $levelCounts[$levelKey] = ($levelCounts[$levelKey] ?? 1) - 1;
                    }

                    if ($dryRun) {
                        continue;
                    }

                    $this->merge($copy, $keeper);
                }
            }
        });

        return $report;
    }

    /**
     * Removes the bank questions listed in database/seeders/data/removed-questions.csv
     * (questions dropped from the starter bank because they repeated another one).
     * Questions already played in a game are deactivated instead, to keep game history.
     */
    public function pruneRetiredBankQuestions(string $path): int
    {
        if (! is_file($path) || ! ($handle = fopen($path, 'r'))) {
            return 0;
        }

        fgetcsv($handle); // header
        $retired = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 2 && trim((string) $row[1]) !== '') {
                $retired[TextKey::make($row[1])] = trim((string) $row[0]);
            }
        }
        fclose($handle);

        if (! $retired) {
            return 0;
        }

        $count = 0;

        Question::query()->with('category:id,name')->get(['id', 'category_id', 'body'])
            ->each(function (Question $q) use ($retired, &$count) {
                $key = TextKey::make($q->body);

                if (! isset($retired[$key]) || $retired[$key] !== $q->category?->name) {
                    return;
                }

                if (GameQuestion::where('question_id', $q->id)->exists()) {
                    Question::whereKey($q->id)->update(['is_active' => false]);
                } else {
                    $q->delete();
                }

                $count++;
            });

        return $count;
    }

    private function merge(Question $copy, Question $keeper): void
    {
        GameQuestion::where('question_id', $copy->id)->update(['question_id' => $keeper->id]);

        Question::whereKey($keeper->id)->update([
            'times_used' => DB::raw('times_used + '.(int) $copy->times_used),
        ]);

        // Keep the copy's media files if the keeper references the same paths.
        $shared = array_intersect(
            array_filter(array_map(fn ($f) => $copy->{$f}, Question::MEDIA)),
            array_filter(array_map(fn ($f) => $keeper->{$f}, Question::MEDIA)),
        );

        if ($shared) {
            Question::whereKey($copy->id)->delete(); // no model events → files stay
        } else {
            Question::find($copy->id)?->delete();      // model event cleans its own files
        }
    }

    private function mediaCount(Question $q): int
    {
        return count(array_filter(array_map(fn ($f) => $q->{$f}, Question::MEDIA)));
    }
}
