<?php

namespace App\Filament\Widgets;

use App\Models\WeightInformation;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class AverageWeightsChart extends ChartWidget
{
    protected static ?string $heading = 'Average Chart';

    protected static ?int $sort = 4;

    public $filters = [];

    protected function getData(): array
    {
        $startDate = isset($this->filters['startDate']) ? Carbon::parse($this->filters['startDate']) : null;
        // Default end date to null if not provided
        $endDate = isset($this->filters['endDate']) ? Carbon::parse($this->filters['endDate']) : null;

        $query = WeightInformation::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        // Fetching the average weight difference
        $averageWeightDifference = $query->whereNotNull('weight_difference')->avg('weight_difference');

        // Fetching the average corrected weight
        $averageCorrectedWeight = $query->whereNotNull('corrected_weight')->avg('corrected_weight');

        return [
            'datasets' => [
                [
                    'label' => 'Average Weights',
                    'data' => [
                        $averageWeightDifference,
                        $averageCorrectedWeight,
                    ],
                    'backgroundColor' => [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                    ],
                    'borderColor' => [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => ['Average Weight Difference', 'Average Corrected Weight'],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
