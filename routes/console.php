<?php

use App\Services\QuestionBankBackup;
use App\Services\QuestionDeduplicator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('questions:dedupe {--dry-run : اعرض المكرر بدون حذف}', function (QuestionDeduplicator $deduplicator) {
    $dry = (bool) $this->option('dry-run');

    $retired = $dry ? 0 : $deduplicator->pruneRetiredBankQuestions(database_path('seeders/data/removed-questions.csv'));
    $report = $deduplicator->run($dry);

    $this->info(($dry ? 'معاينة: ' : '')."مجموعات مكررة: {$report['groups']}");
    $this->info(($dry ? 'سيُحذف: ' : 'حُذف: ').$report['removed'].' سؤال مكرر');

    if ($report['kept_for_playability']) {
        $this->warn("أُبقي على {$report['kept_for_playability']} نسخة بفئات أخرى حتى لا تنقص أسئلتها عن الحد الأدنى");
    }

    if ($retired) {
        $this->info("أُزيل {$retired} سؤال من أسئلة البنك القديمة المكررة المعنى");
    }

    foreach ($report['examples'] as $example) {
        $this->line('  • '.$example);
    }
})->purpose('حذف الأسئلة المكررة من قاعدة البيانات');

Artisan::command('questions:backup {--dir= : مجلد الحفظ (الافتراضي storage/app/backups)}', function (QuestionBankBackup $backup) {
    $path = $backup->export($this->option('dir') ?: null);

    $this->info('تم إنشاء النسخة الاحتياطية:');
    $this->line('  '.$path);
    $this->line('  الحجم: '.number_format(filesize($path) / 1048576, 2).' ميغا');
})->purpose('نسخة احتياطية لبنك الأسئلة مع الصور والأصوات (ZIP)');

Artisan::command('questions:restore {file : مسار ملف ZIP}', function (QuestionBankBackup $backup) {
    $file = $this->argument('file');

    if (! is_file($file)) {
        $this->error("الملف مش موجود: {$file}");

        return 1;
    }

    $result = $backup->restore($file);

    $this->info("انضاف {$result['questions']} سؤال و{$result['categories']} فئة و{$result['media']} ملف وسائط.");
    $this->line("تجاهلنا {$result['skipped']} سؤال موجود مسبقاً.");

    return 0;
})->purpose('استعادة بنك الأسئلة من نسخة احتياطية (بدون حذف أي شي موجود)');
