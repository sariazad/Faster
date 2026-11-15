<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Game;
use App\Models\Question;
use App\Filament\Pages\Statistics;
use App\Services\PlayStats;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $playable = Category::playable()->count();

        return [
            Stat::make('الفئات الجاهزة للعب', $playable)
                ->description('من أصل '.Category::count().' فئة')
                ->color($playable >= Game::CATEGORIES_PER_TEAM * 2 ? 'success' : 'danger'),
            Stat::make('الأسئلة', Question::where('is_active', true)->count()),
            Stat::make('الألعاب', Game::count())
                ->description(Game::where('status', Game::FINISHED)->count().' منتهية'),
            Stat::make('جلسات آخر ٧ أيام (كل الألعاب)', (new PlayStats('7d'))->totals()['sessions'])
                ->description('افتح الإحصائيات الكاملة ←')
                ->url(Statistics::getUrl())
                ->color('info'),
        ];
    }
}
