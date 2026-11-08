<?php

namespace Database\Seeders;

use App\Models\AlefYa\Question;
use Illuminate\Database\Seeder;

/**
 * Loads the ألف ياء letters bank (database/seeders/data/alefya-questions.json) into the
 * separate "alefya" database. Safe to run again: questions already there are skipped.
 */
class AlefYaSeeder extends Seeder
{
    public function run(): void
    {
        $items = json_decode(file_get_contents(database_path('seeders/data/alefya-questions.json')), true) ?: [];
        $existing = Question::query()->get(['letter', 'question'])
            ->mapWithKeys(fn ($q) => [$q->letter.'|'.$q->question => true]);

        $added = 0;

        foreach ($items as $item) {
            if (isset($existing[$item['letter'].'|'.$item['question']])) {
                continue;
            }

            Question::create([
                'letter' => $item['letter'],
                'question' => $item['question'],
                'answer' => $item['answer'],
                'options' => $item['options'] ?? null,
                'category' => $item['category'] ?? null,
                'contributor' => $item['contributor'] ?? null,
            ]);

            $existing[$item['letter'].'|'.$item['question']] = true;
            $added++;
        }

        Question::fillMissingOptions();

        $this->command?->info("ألف ياء: انضاف {$added} سؤال (المجموع ".Question::count().').');
    }
}
