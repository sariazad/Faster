<?php

namespace App\Filament\Resources\Akher\ListResource\Pages;

use App\Filament\Resources\Akher\ListResource;
use App\Models\Akher\GameList;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListLists extends ListRecords
{
    protected static string $resource = ListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('استيراد JSON')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalDescription('ملف بنفس صيغة lists/<id>.json. إذا القائمة موجودة بنفس الـ id، عناصرها بتنستبدل.')
                ->form([
                    Forms\Components\FileUpload::make('file')->label('الملف')->disk('local')->directory('imports')
                        ->acceptedFileTypes(['application/json', 'text/plain', 'text/json'])->required(),
                ])
                ->action(function (array $data) {
                    $disk = Storage::disk('local');
                    try {
                        $json = json_decode($disk->get($data['file']), true) ?: [];
                        $list = GameList::fromFile($json);
                        $problems = GameList::problems($json);
                        $n = Notification::make()->title("انستوردت «{$list->title_ar}» (".$list->items()->count().' عنصر)');
                        $problems ? $n->warning()->body(implode("\n", $problems))->persistent()->send() : $n->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('فشل الاستيراد')->body($e->getMessage())->danger()->send();
                    } finally {
                        $disk->delete($data['file']);
                    }
                }),

            Actions\Action::make('validate')
                ->label('فحص القوائم')
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->action(function () {
                    $report = [];
                    foreach (GameList::with('items')->get() as $list) {
                        $problems = GameList::problems($list->toFile());
                        $report[] = ($problems ? '✘ ' : '✔ ').$list->title_ar.($problems ? ': '.implode(' | ', $problems) : '');
                    }
                    Notification::make()->title('نتيجة الفحص')->body(implode("\n", $report) ?: 'ما في قوائم')->persistent()->send();
                }),

            Actions\Action::make('play')->label('افتح اللعبة')->icon('heroicon-o-play')->color('gray')
                ->url(route('akher.home'), shouldOpenInNewTab: true),

            Actions\CreateAction::make(),
        ];
    }
}
