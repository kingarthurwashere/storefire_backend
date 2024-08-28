<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\WeightInformation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ProductsCompareChart extends ChartWidget
{
    protected static ?string $heading = 'Products Comparison Chart';
    protected static ?int $sort = 2;
    public $filters = [];

    protected function getData(): array
    {

        $startDate = isset($this->filters['startDate']) ? Carbon::parse($this->filters['startDate']) : null;
        $endDate = isset($this->filters['endDate']) ? Carbon::parse($this->filters['endDate']) : null;

        $query = WeightInformation::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $totalCount = $query->count();
        $aliexpressCount = (clone $query)->where('store', 'ALIEXPRESS')->count();
        $amazonAECount = (clone $query)->where('store', 'AMAZON_AE')->count();

        return [
            'datasets' => [
                [
                    'label' => 'Product Count',
                    'data' => [$totalCount, $aliexpressCount, $amazonAECount],
                    'backgroundColor' => ['#FFCE56', '#FF6384', '#36A2EB'],
                ],
            ],
            'labels' => ['Total Products', 'AliExpress', 'Amazon AE'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
