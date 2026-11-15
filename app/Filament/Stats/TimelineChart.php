<?php

namespace App\Filament\Stats;

use App\Services\PlayStats;
use Filament\Widgets\ChartWidget;

/** Sessions over time, stacked by game (lives on the statistics page only). */
class TimelineChart extends ChartWidget
{
    public string $period = '30d';

    protected static ?string $pollingInterval = null;

    protected static ?string $heading = 'عدد الجلسات حسب الوقت';

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        return (new PlayStats($this->period))->timeline();
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => [
                'x' => ['stacked' => true, 'grid' => ['display' => false]],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
