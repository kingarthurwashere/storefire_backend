<?php

namespace App\Filament\Widgets;

use App\Models\WeightInformation;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;

class WeightInformationChart extends ChartWidget
{
    protected static ?string $heading = 'Products Chart';

    protected static ?int $sort = 2;

    public $filters = [];

    protected function getData(): array
    {
        $startDate = isset($this->filters['startDate']) ? Carbon::parse($this->filters['startDate']) : Carbon::now()->subYear();
        $endDate = isset($this->filters['endDate']) ? Carbon::parse($this->filters['endDate']) : Carbon::now();

        $data = Trend::model(WeightInformation::class)
            ->between(
                start: $startDate,
                end: $endDate,
            )
            ->perMonth()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Weight Information',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
