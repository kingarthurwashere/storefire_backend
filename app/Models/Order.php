<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function purchased_items(): HasMany
    {
        return $this->hasMany(PurchasedItem::class);
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
     * Get the balance in dollars.
     *
     * @return float
     */
    public function getBalanceAttribute($value)
    {
        return $value / 100;
    }

    /**
     * Set the balance in cents.
     *
     * @param  float  $value
     * @return void
     */
    public function setBalanceAttribute($value)
    {
        $this->attributes['balance'] = ((float)str_replace(',', '', $value)) * 100;
    }

    /**
     * Set the balance in cents.
     *
     * @param  float  $value
     * @return void
     */
    public function setTotalAttribute($value)
    {
        $this->attributes['total'] = ((float)str_replace(',', '', $value)) * 100;
    }

    public function shipping_details(): HasOne
    {
        return $this->hasOne(ShippingInformation::class);
    }

    public function billing_details(): HasOne
    {
        return $this->hasOne(BillingInformation::class);
    }
}
