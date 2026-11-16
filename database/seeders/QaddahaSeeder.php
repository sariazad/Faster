<?php

namespace Database\Seeders;

use App\Models\Qaddaha\Category;
use App\Models\Qaddaha\Question;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads the original قدّها؟ bank (database/seeders/data/qaddaha/bank.json) into the "qaddaha"
 * database. Existing categories and questions are kept as they are (admin edits stay); new ones are added.
 * The admin import uses the same file format.
 */
class QaddahaSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('seeders/data/qaddaha/bank.json')), true);
        [$cats, $added] = self::import($data);
        $this->command?->info("قدّها؟: {$cats} فئة، انضاف {$added} سؤال (المجموع ".Question::count().').');
    }

    /**
     * @param  array{categories: array<int, array{slug: string, name: string, icon?: string, questions: array}>}  $data
     * @return array{0: int, 1: int} categories touched, questions added
     */
    public static function import(array $data): array
    {
        $added = 0;
        $cats = 0;

        DB::connection('qaddaha')->transaction(function () use ($data, &$added, &$cats) {
            foreach ($data['categories'] ?? [] as $i => $c) {
                $slug = trim((string) ($c['slug'] ?? ''));
                $name = trim((string) ($c['name'] ?? ''));
                if ($slug === '' || $name === '') {
                    continue;
                }
                $category = Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'icon' => $c['icon'] ?? null, 'sort' => ($i + 1) * 10]);
                $cats++;

                foreach ($c['questions'] ?? [] as $q) {
                    $text = trim((string) ($q['text'] ?? ''));
                    $level = (int) ($q['level'] ?? 0);
                    $answers = array_values(array_unique(array_filter(array_map(fn ($a) => trim((string) $a), $q['answers'] ?? []))));
                    if ($text === '' || ! in_array($level, Question::LEVELS, true) || count($answers) < 3) {
                        continue;
                    }
                    $question = Question::firstOrCreate(['category_id' => $category->id, 'text' => $text], ['level' => $level, 'answers' => $answers]);
                    $added += $question->wasRecentlyCreated ? 1 : 0;
                }
            }
        });

        return [$cats, $added];
    }
}
