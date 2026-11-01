<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Game;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports questions from a CSV file (UTF-8, Excel "CSV UTF-8" works).
 *
 * Header row (Arabic or English):
 *   الفئة / category, النقاط / points, السؤال / question, الإجابة / answer
 *
 * Unknown categories are created automatically.
 */
class QuestionCsvImporter
{
    private const HEADERS = [
        'category' => ['category', 'الفئة', 'الفئه'],
        'points' => ['points', 'النقاط', 'نقاط'],
        'question' => ['question', 'السؤال', 'سؤال'],
        'answer' => ['answer', 'الإجابة', 'الاجابة', 'الإجابه', 'الاجابه', 'جواب'],
    ];

    /**
     * @return array{created: int, skipped: int, categories: int, errors: array<int, string>}
     */
    public function import(string $path): array
    {
        $handle = @fopen($path, 'r');

        if (! $handle) {
            throw new RuntimeException('تعذّر فتح الملف.');
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);
            throw new RuntimeException('الملف فاضي.');
        }

        $map = $this->mapHeader($header);

        $result = ['created' => 0, 'skipped' => 0, 'categories' => 0, 'errors' => []];
        $categories = [];
        $line = 1;

        DB::transaction(function () use ($handle, $map, &$result, &$categories, &$line) {
            while (($row = fgetcsv($handle)) !== false) {
                $line++;

                if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue; // blank line
                }

                $categoryName = trim((string) ($row[$map['category']] ?? ''));
                $points = (int) $this->normalizeDigits(trim((string) ($row[$map['points']] ?? '')));
                $body = trim((string) ($row[$map['question']] ?? ''));
                $answer = trim((string) ($row[$map['answer']] ?? ''));

                if ($categoryName === '' || $body === '' || $answer === '') {
                    $result['skipped']++;
                    $result['errors'][] = "سطر {$line}: بيانات ناقصة";

                    continue;
                }

                if (! in_array($points, Game::LEVELS, true)) {
                    $result['skipped']++;
                    $result['errors'][] = "سطر {$line}: النقاط لازم تكون ".implode(' أو ', Game::LEVELS);

                    continue;
                }

                if (! isset($categories[$categoryName])) {
                    $category = Category::firstOrCreate(['name' => $categoryName]);
                    if ($category->wasRecentlyCreated) {
                        $result['categories']++;
                    }
                    $categories[$categoryName] = $category->id;
                }

                $exists = Question::where('category_id', $categories[$categoryName])
                    ->where('body', $body)
                    ->exists();

                if ($exists) {
                    $result['skipped']++;

                    continue;
                }

                Question::create([
                    'category_id' => $categories[$categoryName],
                    'points' => $points,
                    'body' => $body,
                    'answer' => $answer,
                    'is_active' => true,
                ]);

                $result['created']++;
            }
        });

        fclose($handle);

        return $result;
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array<string, int>
     */
    private function mapHeader(array $header): array
    {
        $normalized = array_map(
            fn ($h) => mb_strtolower(trim(str_replace("\u{FEFF}", '', (string) $h))),
            $header
        );

        $map = [];

        foreach (self::HEADERS as $key => $aliases) {
            foreach ($normalized as $index => $value) {
                if (in_array($value, $aliases, true)) {
                    $map[$key] = $index;
                    break;
                }
            }

            if (! isset($map[$key])) {
                throw new RuntimeException('عمود ناقص بالملف: '.$aliases[1].' ('.$aliases[0].')');
            }
        }

        return $map;
    }

    private function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
