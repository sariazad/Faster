<?php

namespace App\Filament\Stats;

use App\Services\PlayStats;
use Filament\Widgets\ChartWidget;

/** Which days of the week people play. */
class WeekdaysChart extends ChartWidget
{
    public string $period = '30d';

    protected static ?string $pollingInterval = null;

    protected static ?string $heading = 'أيام الأسبوع';

    protected static ?string $maxHeight = '240px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = (new PlayStats($this->period))->weekdays();

        return [
            'labels' => $days['labels'],
            'datasets' => [[
                'label' => 'جلسات',
                'data' => $days['data'],
                'backgroundColor' => '#3b82f6',
                'borderRadius' => 4,
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
