<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\WeightInformationChart;
use App\Filament\Widgets\AverageWeightsChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\widgets\ProductsCompareChart;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'Dashboard';

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Start Date')
                            ->placeholder('Select Start Date'),
                        DatePicker::make('endDate')
                            ->label('End Date')
                            ->placeholder('Select End Date'),

                    ])
                    ->columns(2),
            ]);
    }

    public function widgets(): array
    {
        $filters = $this->getFilters();

        return [
            StatsOverview::make()->filters($filters),
            WeightInformationChart::make()->filters($filters),
            AverageWeightsChart::make()->filters($filters),
            ProductsCompareChart::make()->filters($filters),
        ];
    }

    public static function authorization()
    {
        return function ($request) {
            return $request->user()->isAdmin();
        };
    }

    public function getColumns(): int | string | array
    {
        return 2;
    }
}
