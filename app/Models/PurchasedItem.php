<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasedItem extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function purchased_items(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the total in dollars.
     *
     * @return float
     */
    public function getTotalAttribute($value)
    {
        return $value / 100;
    }

    /**
     * Set the total in cents.
     *
     * @param  float  $value
     * @return void
     */
    public function setTotalAttribute($value)
    {
        $this->attributes['total'] = ((float)str_replace(',', '', $value)) * 100;
    }
}
