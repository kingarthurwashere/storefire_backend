<?php

namespace App\Filament\Resources\WeightInformationResource\Pages;

use App\Filament\Resources\WeightInformationResource;
use App\Models\WeightInformation;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\User;
use App\Models\UserProfile;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWeightInformation extends ViewRecord
{
    protected static string $resource = WeightInformationResource::class;
    protected static ?string $title = 'Information';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->color('info'),
            Action::make('delete')
                ->requiresConfirmation()
                ->color('danger')
                ->action(fn () => $this->post->delete()),
            Action::make('Accept')->color('success')
                ->action(function (WeightInformation $record) {
                    $record->accept();

                    Notification::make()
                        ->title('Accepted successfully')
                        ->success()
                        ->send();

                    $this->refreshFormData([
                        'weight_accepted',
                    ]);
                })
                ->visible(fn ($record) => $record->weight_accepted == 'NO' || $record->weight_accepted === null)
                ->requiresConfirmation(),
            Action::make('Reject')->color('warning')
                ->action(function (WeightInformation $record) {
                    $record->reject();

                    Notification::make()
                        ->title('Account deactivated')
                        ->success()
                        ->send();

                    $this->refreshFormData([
                        'weight_accepted',
                    ]);
                })
                ->visible(fn ($record) => $record->weight_accepted == 'YES')
                ->requiresConfirmation(),
        ];
    }
}
