<?php

namespace Database\Seeders;

use App\Models\AlefYa\Question;
use Illuminate\Database\Seeder;

/**
 * Loads the ألف ياء letters bank (database/seeders/data/alefya-questions.json) into the
 * separate "alefya" database. Safe to run again: questions already there are skipped.
 * To throw the old bank away and keep only this file: php artisan alefya:replace-bank
 */
class AlefYaSeeder extends Seeder
{
    public static function bankFile(): string
    {
        return database_path('seeders/data/alefya-questions.json');
    }

    public function run(): void
    {
        $added = static::import();

        $this->command?->info("ألف ياء: انضاف {$added} سؤال (المجموع ".Question::count().').');
    }

    /** Inserts the questions of the bank file that aren't in the database yet. Returns how many. */
    public static function import(): int
    {
        $items = json_decode(file_get_contents(static::bankFile()), true) ?: [];
        $existing = Question::query()->get(['letter', 'question'])
            ->mapWithKeys(fn ($q) => [$q->letter.'|'.$q->question => true])->all();

        $now = now();
        $rows = [];

        foreach ($items as $item) {
            $key = $item['letter'].'|'.$item['question'];

            if (isset($existing[$key])) {
                continue;
            }

            $existing[$key] = true;
            $rows[] = [
                'letter' => $item['letter'],
                'question' => $item['question'],
                'answer' => $item['answer'],
                'options' => isset($item['options']) ? json_encode($item['options'], JSON_UNESCAPED_UNICODE) : null,
                'category' => $item['category'] ?? null,
                'contributor' => $item['contributor'] ?? null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Bulk insert: thousands of rows in a few queries (SQLite allows ~999 bindings per statement).
        Question::query()->getConnection()->transaction(function () use ($rows) {
            foreach (array_chunk($rows, 80) as $chunk) {
                Question::query()->insert($chunk);
            }
        });

        Question::fillMissingOptions();

        return count($rows);
    }
}
