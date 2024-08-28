<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeightInformation extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($model) {
            if ($model->isDirty('corrected_weight')) {
                $model->updateWeightStatusAndDifference();
            }
        });
    }

    // Method to update weight status and weight difference
    public function updateWeightStatusAndDifference()
    {
        $determinedWeight = $this->determined_weight;
        $correctedWeight = $this->corrected_weight;

        if ($correctedWeight < $determinedWeight) {
            $this->weight_status = 'OVERWEIGHT';
        } elseif ($correctedWeight > $determinedWeight) {
            $this->weight_status = 'UNDERWEIGHT';
        } else {
            $this->weight_status = 'ACCEPTABLE';
        }

        $this->weight_difference = abs($correctedWeight - $determinedWeight);

        $this->saveQuietly(); // Use saveQuietly to avoid triggering another update event
    }

    public function accept(): array
    {
        $this->weight_accepted = 'YES';
        $this->save();
        return $this->toArray();
    }
    public function reject(): array
    {
        $this->weight_accepted = 'NO';
        $this->save();
        return $this->toArray();
    }
}
