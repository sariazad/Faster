<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Services\QuestionCsvImporter;
use Illuminate\Database\Seeder;

/**
 * Starter question bank: 60 categories, 799 questions (at least four per level).
 *
 * The questions live in database/seeders/data/question-bank.csv — the same
 * format the admin "استيراد CSV" action accepts, so it can be edited in Excel.
 * Category icons, descriptions and order come from data/categories.json.
 *
 * Safe to run again: existing categories keep any edits made in the admin,
 * and questions that already exist (same category + text) are skipped.
 */
class QuestionBankSeeder extends Seeder
{
    public function run(): void
    {
        $dir = database_path('seeders/data');

        $meta = json_decode(file_get_contents("{$dir}/categories.json"), true);

        foreach ($meta as $item) {
            $category = Category::firstOrCreate(
                ['name' => $item['name']],
                [
                    'icon' => $item['icon'],
                    'description' => $item['description'],
                    'sort_order' => $item['sort'],
                    'is_active' => true,
                ],
            );

            // Fill gaps on categories created before this bank existed, without overwriting admin edits.
            $category->fill(array_filter([
                'icon' => $category->icon ? null : $item['icon'],
                'description' => $category->description ? null : $item['description'],
            ]))->save();
        }

        $result = app(QuestionCsvImporter::class)->import("{$dir}/question-bank.csv");

        $this->command?->info("بنك الأسئلة: انضاف {$result['created']} سؤال، وتجاهلنا {$result['skipped']} موجود مسبقاً.");
    }
}
