<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Game;
use App\Models\Question;
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
        ];
    }
}
