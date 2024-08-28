<?php

namespace App\Filament\Resources\WeightInformationResource\Pages;

use App\Filament\Resources\WeightInformationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWeightInformation extends EditRecord
{
    protected static string $resource = WeightInformationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
