<?php

namespace App\Services;

use App\Models\AlefYa\Question;
use App\Support\AlefYaLetters;

/** Import / export of the ألف ياء bank as JSON: [{letter, question, answer, options, category, contributor}]. */
class AlefYaBank
{
    public function export(): string
    {
        $items = Question::query()->orderBy('letter')->orderBy('id')->get()->map(fn (Question $q) => array_filter([
            'letter' => $q->letter,
            'question' => $q->question,
            'answer' => $q->answer,
            'options' => $q->options,
            'category' => $q->category,
            'contributor' => $q->contributor,
            'active' => $q->is_active ? null : false,
        ], fn ($v) => $v !== null));

        return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * @return array{added: int, skipped: int}
     */
    public function import(string $json): array
    {
        $items = json_decode($json, true);

        if (! is_array($items)) {
            throw new \InvalidArgumentException('الملف مش JSON صالح.');
        }

        $existing = Question::query()->get(['letter', 'question'])->mapWithKeys(fn ($q) => [$q->letter.'|'.$q->question => true]);
        $added = $skipped = 0;

        foreach ($items as $item) {
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));
            $letter = $item['letter'] ?? AlefYaLetters::of($answer);

            if ($question === '' || $answer === '' || ! in_array($letter, AlefYaLetters::ALL, true) || isset($existing["$letter|$question"])) {
                $skipped++;

                continue;
            }

            Question::create([
                'letter' => $letter,
                'question' => $question,
                'answer' => $answer,
                'options' => is_array($item['options'] ?? null) ? array_values($item['options']) : null,
                'category' => $item['category'] ?? null,
                'contributor' => $item['contributor'] ?? null,
                'is_active' => ($item['active'] ?? true) !== false,
            ]);

            $existing["$letter|$question"] = true;
            $added++;
        }

        Question::fillMissingOptions();

        return compact('added', 'skipped');
    }
}
