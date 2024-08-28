<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WeightInformationResource\Pages;
use App\Filament\Resources\WeightInformationResource\RelationManagers;
use App\Models\WeightInformation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Components\TextEntry;

class WeightInformationResource extends Resource
{
    protected static ?string $model = WeightInformation::class;
    protected static ?string $navigationLabel = 'Weight Information';

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\TextInput::make('product_name'),
                                Forms\Components\Select::make('weight_accepted')
                                    ->options([
                                        'null' => 'Unhandled',
                                        'YES' => 'YES',
                                        'NO' => 'NO',
                                    ])->native(false),
                                Forms\Components\TextInput::make('determined_weight')->disabled(true),
                                Forms\Components\TextInput::make('corrected_weight')
                                    ->numeric()
                                    ->afterStateUpdated(function (string $operation, $state, callable $set, callable $get) {

                                        if ($state < $get('determined_weight')) {
                                            $set('weight_status', 'OVERWEIGHT');
                                        }

                                        if ($state > $get('determined_weight')) {
                                            $set('weight_status', 'UNDERWEIGHT');
                                        }

                                        if ($state === $get('determined_weight')) {
                                            $set('weight_status', 'ACCEPTABLE');
                                        }

                                        $weight_difference = abs($get('determined_weight') - $state);
                                        $set('weight_difference', number_format($weight_difference, 2));
                                    }),
                                Forms\Components\TextInput::make('store')->disabled(true),
                                Forms\Components\TextInput::make('url')->disabled(true),
                                Forms\Components\TextInput::make('weight_status')->disabled(true),
                                Forms\Components\TextInput::make('weight_source')->disabled(true),

                                Forms\Components\TextInput::make('weight_machine_notes')
                                    ->disabled(true),
                                Forms\Components\TextInput::make('weight_difference')
                                    ->disabled(true),
                            ])->columns(2)
                    ])->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        return \Illuminate\Support\Str::limit($state, 50);
                    }),
                Tables\Columns\TextColumn::make('determined_weight')->searchable(),
                Tables\Columns\TextColumn::make('store')->searchable(),
                Tables\Columns\TextColumn::make('weight_accepted')->searchable(),
                Tables\Columns\TextColumn::make('weight_status')->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('product_name'),
                TextEntry::make('determined_weight'),
                TextEntry::make('store'),
                TextEntry::make('url'),
                TextEntry::make('weight_status'),
                TextEntry::make('weight_source'),
                TextEntry::make('weight_machine_notes'),

                TextEntry::make('weight_accepted'),
                TextEntry::make('corrected_weight'),
                TextEntry::make('weight_difference'),
            ]);
    }

    protected function getRecord()
    {
        return parent::getRecord();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWeightInformation::route('/'),
            'create' => Pages\CreateWeightInformation::route('/create'),
            'edit' => Pages\EditWeightInformation::route('/{record}/edit'),
            'view' => Pages\ViewWeightInformation::route('/{record}'),
        ];
    }
}
