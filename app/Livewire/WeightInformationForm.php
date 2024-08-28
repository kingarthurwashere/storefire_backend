<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Models\WeightInformation;

class WeightInformationForm extends Component implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    public $product_name;
    public $weight_accepted;
    public $determined_weight;
    public $corrected_weight;
    public $store;
    public $url;
    public $weight_status;
    public $weight_source;
    public $weight_machine_notes;
    public $weight_difference;

    public WeightInformation $record;

    protected function rules()
    {
        return [
            'product_name' => 'required|string|max:255',
            'weight_accepted' => 'nullable|string',
            'determined_weight' => 'nullable|numeric',
            'corrected_weight' => 'nullable|numeric',
            'store' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:255',
            'weight_status' => 'nullable|string|max:255',
            'weight_source' => 'nullable|string|max:255',
            'weight_machine_notes' => 'nullable|string|max:255',
            'weight_difference' => 'nullable|numeric',
        ];
    }

    public function mount($record = null)
    {
        if ($record) {
            $this->record = $record;
            $this->fill($record->toArray());
        }
    }

    public function updatedCorrectedWeight($value)
    {
        $determinedWeight = $this->determined_weight;
        
        if ($value < $determinedWeight) {
            $this->weight_status = 'UNDERWEIGHT';
        } elseif ($value > $determinedWeight) {
            $this->weight_status = 'OVERWEIGHT';
        } else {
            $this->weight_status = 'NORMAL';
        }
        
        $this->weight_difference = $value - $determinedWeight;
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('product_name')
                ->required(),
            Select::make('weight_accepted')
                ->options([
                    'null' => 'Unhandled',
                    'YES' => 'YES',
                    'NO' => 'NO',
                ])->native(false),
            TextInput::make('determined_weight')
                ->disabled(true),
            TextInput::make('corrected_weight')
                ->numeric()
                ->reactive()
                ->afterStateUpdated(fn($state) => $this->updatedCorrectedWeight($state)),
            TextInput::make('store')
                ->disabled(true),
            TextInput::make('url')
                ->disabled(true),
            TextInput::make('weight_status')
                ->disabled(true),
            TextInput::make('weight_source')
                ->disabled(true),
            TextInput::make('weight_machine_notes')
                ->disabled(true),
            TextInput::make('weight_difference')
                ->disabled(true),
        ];
    }

    public function render()
    {
        return view('livewire.weight-information-form');
    }
}
