<?php

namespace App\Filament\Resources\AlefYa\QuestionResource\Pages;

use App\Filament\Resources\AlefYa\QuestionResource;
use App\Models\AlefYa\Question;
use App\Services\AlefYaBank;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ManageQuestions extends ManageRecords
{
    protected static string $resource = QuestionResource::class;

    public function getSubheading(): ?string
    {
        $summary = Question::lettersSummary();
        $thin = collect(\App\Support\AlefYaLetters::ALL)->filter(fn ($l) => ($summary[$l] ?? 0) < 5);

        return 'المجموع: '.Question::count().' سؤال'.($thin->isNotEmpty() ? ' — حروف أسئلتها أقل من 5: '.$thin->implode('، ') : '');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('تصدير JSON')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => response()->streamDownload(
                    fn () => print (app(AlefYaBank::class)->export()),
                    'alefya-questions-'.now()->format('Y-m-d').'.json',
                    ['Content-Type' => 'application/json; charset=utf-8'],
                )),

            Actions\Action::make('import')
                ->label('استيراد JSON')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalDescription('ملف JSON بنفس شكل التصدير: [{"question": "...", "answer": "...", "letter": "ب", "options": [...]}]. الأسئلة الموجودة بتتجاهل.')
                ->form([
                    Forms\Components\FileUpload::make('file')->label('الملف')->disk('local')->directory('imports')
                        ->acceptedFileTypes(['application/json', 'text/plain', 'text/json'])->required(),
                ])
                ->action(function (array $data) {
                    $disk = Storage::disk('local');

                    try {
                        $r = app(AlefYaBank::class)->import($disk->get($data['file']));
                        Notification::make()->title("انضاف {$r['added']} سؤال، وتجاهلنا {$r['skipped']}")->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('فشل الاستيراد')->body($e->getMessage())->danger()->send();
                    } finally {
                        $disk->delete($data['file']);
                    }
                }),

            Actions\Action::make('play')
                ->label('افتح اللعبة')
                ->icon('heroicon-o-play')
                ->color('gray')
                ->url(route('alefya.home'), shouldOpenInNewTab: true),

            Actions\CreateAction::make(),
        ];
    }
}
