<?php

namespace App\Filament\Widgets;

use App\Models\WeightInformation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    public $filters = [];

    protected function getStats(): array
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

        $totalRecords = $query->count();
        $pendingCount = $query->where('weight_status', 'PENDING')->count();
        $acceptableCount = $query->where('weight_status', 'ACCEPTABLE')->count();
        $underweightCount = $query->where('weight_status', 'UNDERWEIGHT')->count();
        $overweightCount = $query->where('weight_status', 'OVERWEIGHT')->count();
        $aliexpressCount = $query->where('store', 'ALIEXPRESS')->count();
        $amazonAECount = $query->where('store', 'AMAZON_AE')->count();
        $noonCount = $query->where('store', 'NOON')->count();
        $averageWeightDifference = $query->whereNotNull('weight_difference')->avg('weight_difference');
        $averageCorrectedWeight = $query->whereNotNull('corrected_weight')->avg('corrected_weight');

        $stats = [
            Stat::make('Total Products', $totalRecords)
                ->description('increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([2, 3, 4, 5, 6, 7])
                ->color('info'),
            Stat::make('Weight Status Pending Products', $pendingCount)
                ->description('increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([4, 3, 3, 3, 3, 3])
                ->color('primary'),
            Stat::make('Weight Status Acceptable Products', $acceptableCount)
                ->description('increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 3, 4, 5, 6, 3])
                ->color('primary'),
            Stat::make('Weight Status Underweight Products', $underweightCount)
                ->description('Must decrease')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->chart([7, 3, 4, 5, 6, 3])
                ->color('danger'),
            Stat::make('Weight Status Overweight Products', $overweightCount)
                ->description('Must decrease')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->chart([7, 3, 4, 5, 6, 3])
                ->color('danger'),
            Stat::make('ALIEXPRESS Products', $aliexpressCount)
                ->description('increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 3, 4, 5, 6, 3])
                ->color('info'),
            Stat::make('AMAZON_AE Products', $amazonAECount)
                ->description('increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 3, 4, 5, 6, 3])
                ->color('info'),
            Stat::make('NOON Products', $noonCount)
                ->description('increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 3, 4, 5, 6, 3])
                ->color('info'),
            Stat::make('Average Weight Difference', $averageWeightDifference)
                ->description('average')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 3, 4, 5, 6, 3])
                ->color('gray'),
            Stat::make('Average Corrected Weight', $averageCorrectedWeight)
                ->description('average')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 3, 4, 5, 6, 3])
                ->color('gray'),
        ];

        // Check if the user is not an admin or editor, then exclude certain stats
        if (!Auth::user()->isAdmin() && !Auth::user()->isEditor()) {
            unset($stats[8]);
            unset($stats[9]);
            unset($stats[10]);
            unset($stats[11]);
            unset($stats[12]);
        }

        return $stats;
    }
}
