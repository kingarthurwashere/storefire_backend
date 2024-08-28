<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log; // Add Log facade
use App\Interfaces\EcommerceScraperInterface;
use App\Models\AliExpressItem;
use App\Models\OrderItemAttempt;
use App\Models\WeightInformation;
use App\Utilities\BaseUtil;
use Mockery\Undefined;

class AliExpressScraperService implements EcommerceScraperInterface
{
    protected $client;
    protected $cachePrefix;
    protected $cacheTime; // Time in minutes to keep data in cache

    private $weight_source = 'STORE';
    private $weight_machine_notes;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => config('app.woocommerce_url'),
            'headers' => [
                'Authorization' => 'Basic ' . config('app.woocommerce_api_token'),
                'Content-Type' => 'application/json',
            ],
        ]);
        $this->cacheTime = config('app.cache_time'); // in minutes
        $this->cachePrefix = config('app.aliexpress_cache_prefix'); // in minutes
    }

    public function attemptScrape(string $url)
    {
        $given_url = BaseUtil::reconstructSiteUrl($url);

        $scrape_resp = $this->scrape($given_url);

        return $scrape_resp;
    }

    public function retrieveUploadedProduct(string $identifier)
    {
        $item = AliExpressItem::where('file_id', $identifier)->first();
        if ($item) {
            return [
                'success' => true,
                'message' => 'Data retrieved successfully',
                'data' => [
                    'identifier' => $identifier,
                    'item' => $item
                ]
            ];
        }
        return [
            'success' => false,
            'message' => 'Item not found',
            'data' => [
                'identifier' => $identifier,
                'item' => null
            ]
        ];
    }

    private function scrape(string $url)
    {
        $identifier = $this->extractLastPartOfUrl($url);
        $cache_key = config('app.aliexpress_cache_prefix') . $identifier;

        // First check the cache
        if (Cache::has($cache_key)) {
            return $this->handleRetrievedData(Cache::get($cache_key), $identifier, $url);
        }

        // Then check the database
        $item = AliExpressItem::where('file_id', $identifier)->first();
        if ($item) {
            return $this->handleRetrievedData(json_decode($item->payload, true), $identifier, $url);
        }

        // Attempt to scrape and cache if not found
        if ($this->scrapeAndCacheData($identifier, $url, $cache_key)) {
            return $this->handleRetrievedData(Cache::get($cache_key), $identifier, $url);
        }

        // Failure to retrieve or scrape data
        return [
            'success' => false,
            'message' => 'Failed to read data from both cache and live scrape',
            'data' => []
        ];
    }

    private function scrapeAndCacheData($identifier, $url, $cache_key)
    {
        $item_url = config('app.aliexpress_prefix') . $identifier;
        $scraped_success = $this->pullSiteData($item_url);

        // Assuming pullSiteData attempts to cache the data if scraping is successful
        return $scraped_success && Cache::has($cache_key);
    }

    private function handleRetrievedData($data, $identifier, $url)
    {
        // Calculation of the item weight with a fallback and multiplier.
        $item_weight = $data['weight'] ?? $this->determineWeight($data) * 1.25;

        if ($item_weight === 0.5) {
            // return [
            //     'success' => false,
            //     'message' => 'Sorry, this item is ineligible for a sale',
            //     'data' => []
            // ];
        }


        // Determine the price from data, using 'maxPrice' as a priority.
        $scraped_price = $data['maxPrice'] ?? $data['price'];

        // Calculate the final price based on the scraped price, shipping price, and item weight.
        $price = $this->calculatePrice($scraped_price, $data['shipping_price'], $item_weight);

        // Map over the priceList array to create a new price list array.
        $new_price_list = array_map(function ($pl) use ($price, $scraped_price) {
            return [
                'skuPropIds' => $pl['skuPropIds'],
                'dxb_price' => isset($pl['skuVal']['skuActivityAmount']) ?
                    number_format($pl['skuVal']['skuActivityAmount']['value'] * (float)str_replace(',', '', $price) / $scraped_price, 2) :
                    number_format($pl['skuVal']['skuAmount']['value'] * (float)str_replace(',', '', $price) / $scraped_price, 2),
            ];
        }, $data['priceList']);


        $final_data = [
            'identifier' => $identifier,
            'item' => array_merge($data, [
                'dxb_price' => $price,  // Corrected to use the calculated `price` for consistency.
                'dxb_sku_price_list' => $new_price_list,  // Updated to use the mapped new price list.
                'file_id' => $identifier,
                'item_weight' => $item_weight,
                'scraped_url' => $url
            ])
        ];

        try {
            WeightInformation::create([
                'product_name' => $final_data['item']['title'],
                'determined_weight' => $final_data['item']['item_weight'],
                'store' => 'ALIEXPRESS',
                'url' => $final_data['item']['url'],
                'weight_source' => $this->weight_source,
                'weight_machine_notes' => $this->weight_source !== 'STORE' ? $this->weight_machine_notes : null,
                'payload' => json_encode($final_data['item'])
            ]);
        } catch (\Throwable $th) {
            //throw $th;
        } finally {
            // Increment the views column
            WeightInformation::where('url', $final_data['item']['url'])
                ->increment('views');
        }

        // Return the response array, including newly calculated and formatted data.
        return [
            'success' => true,
            'message' => 'Data retrieved successfully',
            'data' => $final_data
        ];
    }


    private function applyPriceCalculationToSkuPriceList($price_list,)
    {
    }

    public function determineWeight($payload)
    {
        // Check if payload is a JSON-encoded string and decode it to an array
        if (is_string($payload)) {
            $payload = json_decode($payload, true); // true converts JSON object to associative array
        }

        // Initialize default weight
        $item_weight = 2; // Default weight in kilograms if no dimensions are provided

        // Check if 'title' key exists in the payload and then proceed
        if (isset($payload['title'])) {
            $title = $payload['title'];

            // Pass the title to BaseUtil::postTitleToEndpoint() to get the item weight
            //$item_weight = BaseUtil::postTitleToEndpoint($title);
            $item_weight = BaseUtil::searchAmazonForSimilarItem($title);

            if ($item_weight !== null) {
                $this->weight_source = 'WEIGHT_MACHINE';
                $this->weight_machine_notes = 'AMAZON';
            }
        }

        // Return the formatted item weight
        return number_format($item_weight, 2);
    }

    public function calculatePrice($price_aed, $shipping_price_aed, $weight)
    {
        Log::debug('Price AED in use', ['price' => $price_aed]);
        //TODO: Remove after implementing: pick item from sku list in storeflex UI
        //apply 1.06 discount contingency
        //$price_aed = $price_aed * env('ALIEXPRESS_VOLATILE_DISCOUNT_ALLOWANCE_FACTOR');
        Log::debug('Price AED DISCOUNT VOLATILE FACTOR', ['factor' => env('ALIEXPRESS_VOLATILE_DISCOUNT_ALLOWANCE_FACTOR')]);

        // Initialize debugging log
        Log::debug('Starting price calculation', ['price_aed' => $price_aed, 'shipping_price_aed' => $shipping_price_aed, 'weight' => $weight]);

        $shipping_price_usd = 0;
        $fixed_commission_per_item_aed = 0;

        if ($price_aed === null) {
            $price_aed = 0;
            Log::debug('Price AED is null. Setting price_aed to 0.');
        }

        if ($shipping_price_aed === null) {
            //$shipping_price_aed = env('ALIEXPRESS_DEFAULT_SHIPPING_PRICE'); //default to AED15 but in reality is FREE SHIPPING
            $shipping_price_aed = 0; //default to AED15
            Log::debug('Shipping price AED is null. Setting shipping_price_aed to 0.');
        }

        if ($weight >= 1) {
            Log::debug('Weight is greater than 1kg.', ['initial_weight' => $weight]);
            //apply increase in contingency weight
            Log::debug('Weight in use weight', ['bumped_weight' => $weight]);
            Log::debug('Weight bump factor in use', ['factor' => env('ALIEXPRESS_WEIGHT_BUMP_UP_FACTOR')]);
            $weight = $weight * env('ALIEXPRESS_WEIGHT_BUMP_UP_FACTOR');
            Log::debug('Applied weight bump up factor.', ['bumped_weight' => $weight]);
        }

        // if ($price_aed > 100) {
        //     //add standard AED15 per item charge
        //     $fixed_commission_per_item_aed = config('app.fixed_commission_per_item_aed');
        //     \Log::debug('Fixed commission per item applied.', ['fixed_commission_per_item_aed' => $fixed_commission_per_item_aed]);
        // }

        $weight_calculation = $weight * (config('app.shipping_price_aed_per_kg') / config('app.aed_rate_to_usd'));
        $price_with_comm = ($price_aed + $fixed_commission_per_item_aed) / config('app.aed_rate_to_usd');
        Log::debug('Calculated price with commission.', ['price_with_comm' => $price_with_comm]);

        if ($shipping_price_aed !== 0) {
            $shipping_price_usd = $shipping_price_aed / config('app.aed_rate_to_usd');
            Log::debug('Calculated shipping price USD.', ['shipping_price_usd' => $shipping_price_usd]);
        }

        $overall_commission_to_use = $price_aed >= env('DISCOUNT_ELIGIBLE_AED_PRICE') ? env('DISCOUNTED_OVERALL_COMMISSION_PERCENTAGE') : config('app.overall_commission_percentage');
        Log::debug('Overall commission percentage used.', ['overall_commission_to_use' => $overall_commission_to_use]);

        $total_price = (($weight_calculation + $price_with_comm) * (1 + ($overall_commission_to_use / 100))) + $shipping_price_usd;
        Log::debug('Final total price calculated.', ['total_price' => $total_price]);

        return number_format($total_price, 2);
    }

    public function view(string $identifier)
    {
        $url = config('app.aliexpress_prefix') . $identifier;

        return $this->scrape($url);
    }

    public function pullSiteData($url)
    {
        // Prepare data
        $payload = [
            "url" => $url,
            "platform" => "aliexpress"
        ];

        try {
            $response = Http::withHeaders([
                "Content-Type" => "application/json",
                "Location" => "United Arab Emirates"
            ])
                ->timeout(120) // Set timeout to 60 seconds
                ->post(config('app.scrape_api_endpoint'), $payload); // Adjust timeout as needed


            // Check if the request was successful and the necessary keys exist in the response
            if ($response->successful() && isset($response['url'])) {
                // Extract the required data from the response
                $url = $response['url'];
                $content = $response->json();

                // Generate a unique key for this payload
                $key = config('app.aliexpress_cache_prefix') . $this->extractLastPartOfUrl($url);

                // Store the content in Cache and adjust the duration as needed.
                if (Cache::put($key, $content, now()->addMinutes(config('app.cache_time')))) {
                    return [
                        'success' => true,
                        'message' => 'Data stored in cache successfully'
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Failed to store data in cache'
                    ];
                }
            } else {

                // Handle the error or unsuccessful response accordingly
                return [
                    'success' => false,
                    'message' => 'Failed to retrieve content from the URL'
                ];
            }
        } catch (\Exception $e) {

            return [
                'success' => false,
                'message' => 'Exception occurred while scraping the URL'
            ];
        }
    }

    public function retrieveScrapedDataFromCache(string $identifier)
    {
        return Cache::get($identifier, false);
    }

    public function uploadDataToShop($data, $uuid = null, $quantity = 1, $groupForOrder = false): mixed
    {

        //Check if already uploaded
        $logged_item = AliExpressItem::where('file_id', $data['file_id'])->first();
        Log::info('UPLOADING ALIEXPRESS ITEM WITH PAYLOAD');
        Log::info($data);
        Log::info('PAYLOAD');
        Log::info($data['payload']);

        if ($logged_item) {
            Log::info('UPLOADED ALIEXPRESS ITEM FOUND');
            Log::info($logged_item);

            BaseUtil::updateItemInWoocommerce($logged_item->woocommerce_product_id, [
                'sale_price' => null,
                'regular_price' => $data['payload']['regular_price']
            ]);

            if ($groupForOrder) {
                OrderItemAttempt::create([
                    'uuid' => $uuid,
                    'woocommerce_product_id' => $logged_item->woocommerce_product_id,
                    'quantity' => $quantity
                ]);
            }

            return [
                'success'   =>  true,
                'message'   =>  'Item uploaded',
                'data'    =>  [
                    'link'   =>  $logged_item->woocommerce_link,
                    'woocommerce_product_id' => $logged_item->woocommerce_product_id
                ]
            ];
        }

        Log::info('ALIEXPRESS ITEM NOT FOUND IN DB');
        Log::info('STARTING UPLOADING ALIEXPRESS ITEM WITH PAYLOAD');
        Log::info($data['payload']);

        $uploaded_product = BaseUtil::postItemToWoocommerce($data['payload']);
        $uploaded_product_ = json_decode($uploaded_product);

        Log::info('WOOCOMMERCE RESPONSE');
        Log::info((array)$uploaded_product_);

        if ($uploaded_product_ && isset($uploaded_product_->permalink)) {

            //log to aliexpress_item
            $ai = new AliExpressItem();
            $ai->site_link = $data['url'];
            $ai->url_hash = $data['url'];
            $ai->description = $uploaded_product_->description;
            $ai->price_usd = (int)((float)str_replace($uploaded_product_->price, ',', '') * 100);
            $ai->price_aed = (int)((float)str_replace($uploaded_product_->price, ',', '') * env('AED_RATE_TO_USD') * 100);
            $ai->woocommerce_product_id = $uploaded_product_->id;
            $ai->url = $data['url'];
            $ai->woocommerce_link = $uploaded_product_->permalink;
            $ai->payload = json_encode(json_decode($data['session'], true));
            $ai->file_id = $data['file_id'];

            if ($ai->save()) {

                if ($groupForOrder) {
                    OrderItemAttempt::create([
                        'uuid' => $uuid,
                        'woocommerce_product_id' => $uploaded_product_->id,
                        'quantity' => $quantity
                    ]);
                }
                return [
                    'success'   =>  true,
                    'message'   =>  'Item uploaded',
                    'data'    =>  [
                        'link'   =>  $ai->woocommerce_link,
                        'woocommerce_product_id' => $uploaded_product_->id
                    ]
                ];
            } else {
                return [
                    'success'   =>  false,
                    'message'   =>  'Failed to upload item!!',
                    'data'    =>  $ai->errors
                ];
            }
        } else {
            return [
                'success'   =>  false,
                'message'   =>  'Failed to upload item!',
                'data'    =>  $uploaded_product_
            ];
        }
    }

    public static function isSiteUrl(string $url): bool
    {
        $expectedPrefix = config('app.aliexpress_url');

        $result = explode($expectedPrefix, $url);
        return count($result) === 2;
    }

    /***************************************/
    /** custom methods **/
    /***************************************/

    private function extractLastPartOfUrl($url)
    {
        // Parse the URL to get the path component
        $parsedUrl = parse_url($url, PHP_URL_PATH);

        // Get the base name of the path
        $lastPart = basename($parsedUrl);

        return $lastPart;
    }
}
