<?php

namespace App\Filament\Resources\WeightInformationResource\Pages;

use App\Filament\Resources\WeightInformationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWeightInformation extends ListRecords
{
    protected static string $resource = WeightInformationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
