<?php

namespace App\Filament\Stats;

use App\Services\PlayStats;
use Filament\Widgets\ChartWidget;

/** What time of day people play (Riyadh time). */
class HoursChart extends ChartWidget
{
    public string $period = '30d';

    protected static ?string $pollingInterval = null;

    protected static ?string $heading = 'أوقات اللعب خلال اليوم';

    protected static ?string $maxHeight = '240px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $hours = (new PlayStats($this->period))->hours();

        return [
            'labels' => $hours['labels'],
            'datasets' => [[
                'label' => 'جلسات',
                'data' => $hours['data'],
                'backgroundColor' => '#f59e0b',
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
