<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Backs up and restores the whole question bank as one ZIP file:
 *
 *   manifest.json     version, date and counts
 *   categories.json   every category (name, icon, description, order, active, image)
 *   questions.json    every question (all fields, including media paths)
 *   questions.csv     the same questions in the admin import format, for Excel
 *   media/…           every picture and sound the bank uses, at its storage path
 *
 * Restoring merges: categories are matched by name, questions by category +
 * text + picture/sound. Nothing that already exists is changed or deleted,
 * so restoring the same backup twice adds nothing the second time.
 */
class QuestionBankBackup
{
    public const VERSION = 1;

    private const MEDIA_FIELDS = ['image', 'audio', 'answer_image', 'answer_audio'];

    /** Builds a backup ZIP and returns its absolute path. */
    public function export(?string $directory = null): string
    {
        $directory ??= storage_path('app/backups');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("ما قدرنا ننشئ المجلد {$directory}");
        }

        $path = rtrim($directory, '/').'/man-alasbaq-backup-'.now()->format('Y-m-d_His').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ما قدرنا ننشئ ملف النسخة الاحتياطية.');
        }

        $categories = Category::query()->orderBy('sort_order')->orderBy('id')->get();
        $names = $categories->pluck('name', 'id');

        $questions = Question::query()->orderBy('category_id')->orderBy('points')->orderBy('id')->get();

        $media = [];

        $categoryRows = $categories->map(function (Category $c) use (&$media) {
            if ($c->image) {
                $media[$c->image] = true;
            }

            return [
                'name' => $c->name,
                'icon' => $c->icon,
                'description' => $c->description,
                'image' => $c->image,
                'is_active' => (bool) $c->is_active,
                'sort_order' => (int) $c->sort_order,
            ];
        })->values()->all();

        $questionRows = $questions->map(function (Question $q) use ($names, &$media) {
            foreach (self::MEDIA_FIELDS as $field) {
                if ($q->{$field}) {
                    $media[$q->{$field}] = true;
                }
            }

            return [
                'category' => $names[$q->category_id] ?? null,
                'points' => (int) $q->points,
                'body' => $q->body,
                'answer' => $q->answer,
                'image' => $q->image,
                'audio' => $q->audio,
                'answer_image' => $q->answer_image,
                'answer_audio' => $q->answer_audio,
                'is_active' => (bool) $q->is_active,
                'times_used' => (int) $q->times_used,
            ];
        })->filter(fn ($row) => $row['category'] !== null)->values()->all();

        $disk = Storage::disk('public');
        $missing = 0;
        foreach (array_keys($media) as $file) {
            if ($disk->exists($file)) {
                $zip->addFile($disk->path($file), 'media/'.$file);
            } else {
                $missing++;
            }
        }

        $json = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

        $zip->addFromString('manifest.json', json_encode([
            'app' => 'man-alasbaq',
            'version' => self::VERSION,
            'created_at' => now()->toIso8601String(),
            'categories' => count($categoryRows),
            'questions' => count($questionRows),
            'media_files' => count($media) - $missing,
            'missing_media_files' => $missing,
        ], $json));
        $zip->addFromString('categories.json', json_encode($categoryRows, $json));
        $zip->addFromString('questions.json', json_encode($questionRows, $json));
        $zip->addFromString('questions.csv', $this->csv($questionRows));

        $zip->close();

        return $path;
    }

    /**
     * @return array{categories: int, questions: int, skipped: int, media: int}
     */
    public function restore(string $zipPath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('الملف مش ملف ZIP صالح.');
        }

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $categories = json_decode((string) $zip->getFromName('categories.json'), true);
        $questions = json_decode((string) $zip->getFromName('questions.json'), true);

        if (($manifest['app'] ?? null) !== 'man-alasbaq' || ! is_array($categories) || ! is_array($questions)) {
            $zip->close();
            throw new RuntimeException('هاد الملف مش نسخة احتياطية من «مين الأسبق».');
        }

        $result = ['categories' => 0, 'questions' => 0, 'skipped' => 0, 'media' => 0];
        $disk = Storage::disk('public');

        // Media first: copy every file to the same path, renaming if a different file is already there.
        $renamed = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (! str_starts_with($entry, 'media/') || str_ends_with($entry, '/')) {
                continue;
            }

            $relative = substr($entry, strlen('media/'));
            if (! $this->isSafePath($relative)) {
                continue;
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                continue;
            }

            $target = $relative;
            if ($disk->exists($target) && $disk->get($target) !== $contents) {
                $info = pathinfo($relative);
                $target = ($info['dirname'] !== '.' ? $info['dirname'].'/' : '')
                    .$info['filename'].'-'.Str::lower(Str::random(6))
                    .(isset($info['extension']) ? '.'.$info['extension'] : '');
                $renamed[$relative] = $target;
            }

            if (! $disk->exists($target)) {
                $disk->put($target, $contents);
                $result['media']++;
            }
        }
        $zip->close();

        $map = fn (?string $path) => $path === null ? null : ($renamed[$path] ?? $path);

        DB::transaction(function () use ($categories, $questions, $map, &$result) {
            $ids = [];

            foreach ($categories as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $category = Category::firstOrNew(['name' => $name]);
                if (! $category->exists) {
                    $category->fill([
                        'icon' => $row['icon'] ?? null,
                        'description' => $row['description'] ?? null,
                        'image' => $map($row['image'] ?? null),
                        'is_active' => (bool) ($row['is_active'] ?? true),
                        'sort_order' => (int) ($row['sort_order'] ?? 0),
                    ])->save();
                    $result['categories']++;
                } elseif (! $category->image && ! empty($row['image'])) {
                    $category->update(['image' => $map($row['image'])]);
                }

                $ids[$name] = $category->id;
            }

            $existing = Question::query()
                ->get(['id', 'category_id', 'body', 'image', 'audio'])
                ->mapWithKeys(fn (Question $q) => [$q->category_id.'#'.$q->identityKey() => true])
                ->all();

            foreach ($questions as $row) {
                $name = trim((string) ($row['category'] ?? ''));
                $body = trim((string) ($row['body'] ?? ''));

                if ($body === '' || $name === '') {
                    $result['skipped']++;

                    continue;
                }

                $ids[$name] ??= Category::firstOrCreate(['name' => $name])->id;

                $question = new Question([
                    'category_id' => $ids[$name],
                    'points' => (int) ($row['points'] ?? 200),
                    'body' => $body,
                    'answer' => $row['answer'] ?? null,
                    'image' => $map($row['image'] ?? null),
                    'audio' => $map($row['audio'] ?? null),
                    'answer_image' => $map($row['answer_image'] ?? null),
                    'answer_audio' => $map($row['answer_audio'] ?? null),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ]);

                $key = $question->category_id.'#'.$question->identityKey();
                if (isset($existing[$key])) {
                    $result['skipped']++;

                    continue;
                }

                $question->save();
                $existing[$key] = true;
                $result['questions']++;
            }
        });

        return $result;
    }

    /** Media paths in a backup must stay inside the public disk. */
    private function isSafePath(string $path): bool
    {
        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '..')
            && ! str_contains($path, "\0")
            && ! str_contains($path, '\\');
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\u{FEFF}");
        fputcsv($handle, ['الفئة', 'النقاط', 'السؤال', 'الإجابة']);
        foreach ($rows as $row) {
            fputcsv($handle, [$row['category'], $row['points'], $row['body'], $row['answer']]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }
}
