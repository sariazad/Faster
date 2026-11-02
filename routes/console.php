<?php

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
