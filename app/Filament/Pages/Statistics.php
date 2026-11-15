<?php

namespace App\Filament\Pages;

use App\Services\PlayStats;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/** «الإحصائيات»: how much every game is played, for how long, and when. */
class Statistics extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'الإحصائيات';

    protected static ?string $title = 'الإحصائيات';

    protected static ?string $slug = 'statistics';

    protected static ?int $navigationSort = -1;

    protected static string $view = 'filament.pages.statistics';

    #[Url]
    public string $period = '30d';

    public function mount(): void
    {
        $this->period = (new PlayStats($this->period))->period; // unknown values fall back to 30 days
    }

    public function setPeriod(string $period): void
    {
        if (isset(PlayStats::PERIODS[$period])) {
            $this->period = $period;
        }
    }

    protected function getViewData(): array
    {
        $stats = new PlayStats($this->period);

        return [
            'periods' => PlayStats::PERIODS,
            'totals' => $stats->totals(),
            'games' => $stats->perGame(),
            'peaks' => $stats->peaks(),
            'categories' => $stats->topCategories(),
            'lists' => $stats->topLists(),
            'recent' => $stats->recent(),
        ];
    }
}
