<?php

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Resources\QuestionResource;
use App\Services\QuestionCsvImporter;
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

            Actions\CreateAction::make(),
        ];
    }
}
