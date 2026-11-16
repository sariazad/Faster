<?php

namespace App\Filament\Resources\Qaddaha\QuestionResource\Pages;

use App\Filament\Resources\Qaddaha\QuestionResource;
use App\Models\Qaddaha\Question;
use Database\Seeders\QaddahaSeeder;
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
            Actions\Action::make('import')
                ->label('استيراد JSON')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalDescription('نفس صيغة البنك: {"categories":[{"slug","name","icon","questions":[{"level","text","answers":[]}]}]}. الفئات الموجودة بنفس الـ slug بتنضاف عليها الأسئلة الجديدة، والأسئلة المكررة بتنترك.')
                ->form([
                    Forms\Components\FileUpload::make('file')->label('الملف')->disk('local')->directory('imports')
                        ->acceptedFileTypes(['application/json', 'text/plain', 'text/json'])->required(),
                ])
                ->action(function (array $data) {
                    $disk = Storage::disk('local');
                    try {
                        $json = json_decode($disk->get($data['file']), true);
                        throw_unless(is_array($json) && isset($json['categories']), new \RuntimeException('الملف لازم يكون فيه categories'));
                        [$cats, $added] = QaddahaSeeder::import($json);
                        Notification::make()->title("انضاف {$added} سؤال على {$cats} فئة")->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('فشل الاستيراد')->body($e->getMessage())->danger()->send();
                    } finally {
                        $disk->delete($data['file']);
                    }
                }),

            Actions\Action::make('export')
                ->label('تصدير JSON')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => response()->streamDownload(function () {
                    $cats = \App\Models\Qaddaha\Category::with('questions')->orderBy('sort')->get()->map(fn ($c) => [
                        'slug' => $c->slug, 'name' => $c->name, 'icon' => $c->icon,
                        'questions' => $c->questions->map(fn ($q) => ['level' => $q->level, 'text' => $q->text, 'answers' => $q->answers])->values(),
                    ]);
                    echo json_encode(['version' => 1, 'categories' => $cats], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }, 'qaddaha-bank.json', ['Content-Type' => 'application/json; charset=utf-8'])),

            Actions\Action::make('reset')
                ->label('تصفير عدّاد الظهور')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('كل الأسئلة بترجع «جديدة» وبتطلع بالترتيب من أول وجديد.')
                ->action(function () {
                    Question::query()->update(['times_used' => 0]);
                    Notification::make()->title('تصفّر العدّاد')->success()->send();
                }),

            Actions\Action::make('play')->label('افتح اللعبة')->icon('heroicon-o-play')->color('gray')
                ->url(route('qaddaha.home'), shouldOpenInNewTab: true),

            Actions\CreateAction::make(),
        ];
    }
}
