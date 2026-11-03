<?php

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Resources\QuestionResource;
use App\Models\Question;
use App\Services\QuestionBankBackup;
use App\Services\QuestionCsvImporter;
use App\Services\QuestionDeduplicator;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListQuestions extends ListRecords
{
    protected static string $resource = QuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('backup')
                    ->label('تنزيل نسخة احتياطية')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        $path = app(QuestionBankBackup::class)->export();

                        return response()->download($path)->deleteFileAfterSend(true);
                    }),

                Actions\Action::make('restore')
                    ->label('استعادة نسخة احتياطية')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalHeading('استعادة نسخة احتياطية')
                    ->modalDescription('بيضيف الفئات والأسئلة والصور والأصوات اللي مش موجودة. ما بيحذف ولا بيغيّر أي شي موجود، فآمن تستعيد نفس النسخة أكثر من مرة.')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('ملف النسخة (ZIP)')
                            ->disk('local')
                            ->directory('imports')
                            ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'application/octet-stream'])
                            ->maxSize(256000)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $disk = Storage::disk('local');

                        try {
                            $result = app(QuestionBankBackup::class)->restore($disk->path($data['file']));
                        } catch (Throwable $e) {
                            Notification::make()->title('فشلت الاستعادة')->body($e->getMessage())->danger()->send();

                            return;
                        } finally {
                            $disk->delete($data['file']);
                        }

                        Notification::make()
                            ->title('تمت الاستعادة')
                            ->body("انضاف {$result['questions']} سؤال و{$result['categories']} فئة و{$result['media']} ملف وسائط، وتجاهلنا {$result['skipped']} موجود مسبقاً.")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
                ->label('نسخة احتياطية')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->button(),

            Actions\Action::make('template')
                ->label('نموذج CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(asset('samples/questions-template.csv')),

            Actions\Action::make('import')
                ->label('استيراد CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalDescription('الأعمدة: الفئة، النقاط، السؤال، الإجابة. الفئات الجديدة تنضاف تلقائياً والأسئلة المكررة تتجاهل.')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('ملف CSV')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $disk = Storage::disk('local');

                    try {
                        $result = app(QuestionCsvImporter::class)->import($disk->path($data['file']));
                    } catch (Throwable $e) {
                        Notification::make()->title('فشل الاستيراد')->body($e->getMessage())->danger()->send();

                        return;
                    } finally {
                        $disk->delete($data['file']);
                    }

                    $body = "انضاف {$result['created']} سؤال";
                    if ($result['categories']) {
                        $body .= " و {$result['categories']} فئة جديدة";
                    }
                    if ($result['skipped']) {
                        $body .= "، وتجاهلنا {$result['skipped']} سطر";
                    }
                    if ($result['errors']) {
                        $body .= "\n".implode("\n", array_slice($result['errors'], 0, 5));
                    }

                    Notification::make()
                        ->title('تم الاستيراد')
                        ->body($body)
                        ->success()
                        ->persistent()
                        ->send();
                }),

            Actions\Action::make('dedupe')
                ->label('حذف المكرر')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('حذف الأسئلة المكررة')
                ->modalDescription(function () {
                    $preview = app(QuestionDeduplicator::class)->run(dryRun: true);

                    return $preview['removed']
                        ? "لقينا {$preview['removed']} سؤال مكرر. بنحتفظ بنسخة وحدة من كل سؤال (اللي فيها صور أو صوت إن وجدت)، والألعاب السابقة ما بتتأثر."
                        : 'ما في أسئلة مكررة حالياً. بنشيل بس أسئلة البنك القديمة اللي كانت مكررة المعنى إن وجدت.';
                })
                ->action(function () {
                    $service = app(QuestionDeduplicator::class);
                    $retired = $service->pruneRetiredBankQuestions(database_path('seeders/data/removed-questions.csv'));
                    $report = $service->run();

                    $body = "حذفنا {$report['removed']} سؤال مكرر";
                    if ($retired) {
                        $body .= " و{$retired} سؤال قديم مكرر المعنى";
                    }
                    if ($report['kept_for_playability']) {
                        $body .= "، وأبقينا {$report['kept_for_playability']} نسخة حتى ما تنقص فئاتها";
                    }

                    Notification::make()->title('تم التنظيف')->body($body)->success()->send();
                }),

            Actions\Action::make('resetUsage')
                ->label('تصفير عدّاد الظهور')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('كل الأسئلة بترجع كأنها ما ظهرت أبداً، وبتدخل الدورة من جديد. استخدمه مثلاً قبل فعالية مع جمهور جديد.')
                ->action(function () {
                    Question::query()->update(['times_used' => 0, 'last_used_at' => null]);
                    Notification::make()->title('تم تصفير عدّاد الظهور')->success()->send();
                }),

            Actions\CreateAction::make(),
        ];
    }
}
