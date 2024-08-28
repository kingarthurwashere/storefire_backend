<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

enum STOREFLEX_V3_STORE_PREFIX: string
{
    case AMAZON_AE = '/amazon-ae/';
    case ALIEXPRESS = '/aliexpress/';
}

class WeightInformationResource extends JsonResource
{
    private string $storeflexId;
    private string $storeflexLink;
    private string $storeflexStoreName;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        $payload = json_decode($this->payload);

        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'store' => $this->store,
            'store_friendly' => $this->determineFriendlyStoreName($this->store),
            'url' => $this->url,
            'views' => (int)$this->views,
            'storeflex_link' => $this->determineStoreflexLink($this->store, $payload),
            'storeflex_unique_identifier' => $this->determineStoreflexID($this->store, $payload),
            'image' => $payload->images[0],
            'price' => (float)str_replace(',', '', $payload->dxb_price),
        ];
    }

    private function determineStoreflexLink(string $storeKey, $payload): string
    {
        switch ($storeKey) {
            case 'AMAZON_AE':
                $this->storeflexLink = STOREFLEX_V3_STORE_PREFIX::AMAZON_AE->value . $payload->asin;
                break;
            case 'ALIEXPRESS':
                $this->storeflexLink = STOREFLEX_V3_STORE_PREFIX::ALIEXPRESS->value . $payload->file_id;
                break;
            default:
                $this->storeflexLink = '';
                break;
        }

        return $this->storeflexLink;
    }

    private function determineStoreflexID(string $storeKey, $payload): string
    {
        switch ($storeKey) {
            case 'AMAZON_AE':
                $this->storeflexId = $payload->asin;
                break;
            case 'ALIEXPRESS':
                $this->storeflexId = $payload->file_id;
                break;
            default:
                $this->storeflexId = '';
                break;
        }

        return $this->storeflexId;
    }

    private function determineFriendlyStoreName(string $storeKey): string
    {
        switch ($storeKey) {
            case 'AMAZON_AE':
                $this->storeflexStoreName = 'AMAZON';
                break;
            case 'ALIEXPRESS':
                $this->storeflexStoreName = 'ALIEXPRESS';
                break;
            default:
                $this->storeflexStoreName = '';
                break;
        }

        return $this->storeflexStoreName;
    }
}
