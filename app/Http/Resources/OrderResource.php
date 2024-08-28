<?php

namespace App\Http\Resources;

use App\Models\BillingInformation;
use App\Models\ShippingInformation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'woocommerce_order_id' => $this->woocommerce_order_id,
            'status' => $this->status,
            'total' => $this->total,
            'balance' => $this->balance,
            'payment_method' => $this->payment_method,
            'created_at' => $this->created_at->toDateTimeString(),
            'purchased_items' => PurchasedItemResource::collection($this->whenLoaded('purchased_items')),
            'billing_details' => $this->whenLoaded('billing_details', function () {
                return [
                    'first_name' => $this->billing_details->first_name,
                    'last_name' => $this->billing_details->last_name,
                    'email' => $this->billing_details->email,
                    'phone' => $this->billing_details->phone,
                    'address_1' => $this->billing_details->address,
                    'city' => $this->billing_details->city,
                    'country' => $this->billing_details->country,
                ];
            }),
            'shipping_details' => $this->whenLoaded('shipping_details', function () {
                return [
                    'first_name' => $this->shipping_details->first_name,
                    'last_name' => $this->shipping_details->last_name,
                    'email' => $this->shipping_details->email,
                    'phone' => $this->shipping_details->phone,
                    'address_1' => $this->shipping_details->address,
                    'city' => $this->shipping_details->city,
                    'country' => $this->shipping_details->country,
                ];
            }),
        ];
    }
}
