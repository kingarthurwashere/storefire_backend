<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\WeightInformationResource;
use App\Models\WeightInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

enum CacheKeys: string
{
    case RECENT_ITEMS = 'recent_items';
    case TRENDING_ITEMS = 'trending_items';
    case POPULAR_ITEMS = 'popular_items';
    case DISCOVER_ITEMS = 'discover_items';
    case ALIEXPRESS_ITEMS = 'aliexpress_items';
    case AMAZONAE_ITEMS = 'amazonae_items';
}

class MarketingController extends Controller
{
    /**
     * Retrieve items with a creation date no older than the specified age limit.
     *
     * @param int $take
     * @param \Closure $query
     * @param CacheKeys $cacheKey
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    private function getItems($take, $query, CacheKeys $cacheKey)
    {
        // Set cache time to 10 minutes
        $cacheTime = 10; // in minutes

        // Get the record age limit from the environment variable
        $recordAgeLimit = env('CACHE_TIME', 60); // Default to 60 minutes if CACHE_TIME is not set

        // Attempt to retrieve cached data
        $items = Cache::remember($cacheKey->value, $cacheTime * 60, function () use ($take, $query, $recordAgeLimit) {
            // Get the current time
            $currentTime = Carbon::now();

            // Retrieve items from the database using the provided closure
            $items = $query()->get();

            // Filter items to ensure they are within the age limit
            // $filteredItems = $items->filter(function ($item) use ($currentTime, $recordAgeLimit) {
            //     return $currentTime->diffInMinutes($item->created_at) <= $recordAgeLimit;
            // });

            // Limit the number of items to the specified amount
            return $items->take($take);
        });

        return WeightInformationResource::collection($items);
    }

    public function recentItems()
    {
        return $this->getItems(12, function () {
            // Query the items
            return WeightInformation::latest();
        }, CacheKeys::RECENT_ITEMS);
    }

    public function trendingItems(Request $req)
    {
        return $this->getItems(6, function () {
            return WeightInformation::orderBy('views', 'desc');
        }, CacheKeys::TRENDING_ITEMS);
    }

    public function popularItems(Request $req)
    {
        return $this->getItems(18, function () {
            return WeightInformation::orderBy('views', 'desc');
        }, CacheKeys::POPULAR_ITEMS);
    }

    public function discoverItems(Request $req)
    {
        $items = $this->getItems(24, function () {
            return WeightInformation::orderBy('views', 'asc');
        }, CacheKeys::DISCOVER_ITEMS);

        return ['data' => $items->shuffle()];
    }

    public function aliexpressItems(Request $req)
    {
        return $this->getItems(18, function () {
            return WeightInformation::where('store', 'ALIEXPRESS')->orderBy('views', 'asc');
        }, CacheKeys::ALIEXPRESS_ITEMS);
    }

    public function amazonaeItems(Request $req)
    {
        return $this->getItems(18, function () {
            return WeightInformation::where('store', 'AMAZON_AE')->orderBy('views', 'asc');
        }, CacheKeys::AMAZONAE_ITEMS);
    }
}
