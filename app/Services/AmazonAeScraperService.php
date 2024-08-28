<?php

namespace App\Services;

use App\Models\WeightInformation;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Interfaces\EcommerceScraperInterface;
use App\Models\AmazonAeItem;
use App\Models\OrderItemAttempt;
use Illuminate\Support\Facades\Log;
use App\Utilities\BaseUtil;

class AmazonAeScraperService implements EcommerceScraperInterface
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
        $this->cachePrefix = config('app.amazon_ae_cache_prefix'); // in minutes
    }

    public function attemptScrape(string $url)
    {
        $given_url = BaseUtil::reconstructSiteUrl($url);

        $scrape_resp = $this->scrape($given_url);

        return $scrape_resp;
    }

    public function retrieveUploadedProduct($identifier)
    {
        $item = AmazonAeItem::where('asin', $identifier)->first();
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
        $cache_key = config('app.amazon_ae_cache_prefix') . $this->extractASIN($url);
        $data = $this->handleCache($cache_key, $url);

        if (!$data['success']) {
            $item = AmazonAeItem::where('site_link', 'LIKE', "%$url%")->first();
            if ($item && is_array($item->payload)) {
                $data = $this->processItemPayload($item->payload, $url);
            }
        }

        return $this->prepareResponse($data);
    }

    private function processItemPayload($payload, string $url)
    {
        $item_weight = $this->determineWeight($payload);
        $price = $this->calculatePrice($payload['price_upper'], $payload['price_shipping'], $item_weight);
        return [
            'success' => true,
            'message' => 'Not Verified',
            'identifier' => '',
            'item' => array_merge($payload, [
                'dxb_price' => $price,
                'item_weight' => $item_weight,
                'scraped_url' => $url
            ])
        ];
    }

    private function handleCache(string $cache_key, string $url)
    {
        if (Cache::has($cache_key)) {
            return $this->retrieveFromCache($cache_key, $url);
        }

        $asin_url = config('app.amazon_ae_prefix') . $this->extractASIN($url);
        $this->pullSiteData($asin_url);

        if (Cache::has($cache_key)) {
            return $this->retrieveFromCache($cache_key, $url);
        }

        return ['success' => false, 'message' => 'Failed to read data', 'data' => []];
    }

    private function retrieveFromCache(string $cache_key, string $url)
    {
        $retrieved_data = $this->retrieveScrapedDataFromCache($cache_key);
        $item_weight = $this->determineWeight($retrieved_data);
        $price = $this->calculatePrice($retrieved_data['price_upper'], $retrieved_data['price_shipping'], $item_weight);

        return [
            'success' => true,
            'message' => 'Data retrieved successfully',
            'identifier' => $retrieved_data['asin'],
            'item' => array_merge($retrieved_data, [
                'dxb_price' => $price,
                'item_weight' => $item_weight,
                'scraped_url' => $url
            ])
        ];
    }

    private function prepareResponse($data)
    {
        try {
            WeightInformation::create([
                'product_name' => $data['item']['title'],
                'determined_weight' => $data['item']['item_weight'],
                'store' => 'AMAZON_AE',
                'url' => $data['item']['url'],
                'weight_source' => $this->weight_source,
                'weight_machine_notes' => $this->weight_source !== 'STORE' ? $this->weight_machine_notes : null,
                'payload' => json_encode($data['item'])
            ]);
        } catch (\Throwable $th) {
            //throw $th;
        } finally {
            // Increment the views column
            WeightInformation::where('url', $data['item']['url'])
                ->increment('views');
        }

        return [
            'success' => $data['success'],
            'message' => $data['message'],
            'data' => [
                'identifier' => $data['identifier'] ?? '',
                'item' => $data['item'] ?? []
            ]
        ];
    }


    public function determineWeight($payload)
    {
        Log::info('Starting determineWeight function');

        // Extract the outermost category
        $categories = [];
        if (isset($payload['category'][0])) {
            foreach ($payload['category'][0]['ladder'] as $value) {
                $categories[] = $value['name'];
            }
        }

        $outer_category = end($categories); // Use outermost category
        $categories = [$outer_category];
        Log::info('Using outermost category: ' . $outer_category);

        // Early exit if no product details are found
        if (!isset($payload['product_details'])) {
            Log::info('No product details available, using Levenshtein algorithm');
            $item_weight = BaseUtil::applyLevenshteinAlgo($categories);
            if ($item_weight !== null) {
                $this->weight_source = 'WEIGHT_MACHINE';
                $this->weight_machine_notes = 'LEVENSHTEIN';
            }
            return $this->formatWeight($item_weight);
        }

        $details = $payload['product_details'];
        Log::info('Product details found');

        // Check for 'product_dimensions' and return early if a valid weight is found
        if (isset($details['product_dimensions'])) {
            Log::info('Checking product_dimensions: ' . $details['product_dimensions']);
            $potential_weight = BaseUtil::extractWeightInKilograms($details['product_dimensions']);
            Log:
            info('POTENTIAL_WEIGHT: ' . $potential_weight);
            if ($potential_weight !== null) {
                Log::info('Valid weight found in product_dimensions: ' . $potential_weight);
                return $this->formatWeight($potential_weight * 1.25);
            }
        }

        // Check for 'package_dimensions' and return early if a valid weight is found
        if (isset($details['package_dimensions'])) {
            Log::info('Checking package_dimensions: ' . $details['package_dimensions']);
            $potential_weight = BaseUtil::extractWeightInKilograms($details['package_dimensions']);
            if ($potential_weight !== null) {
                Log::info('Valid weight found in package_dimensions: ' . $potential_weight);
                return $this->formatWeight($potential_weight * 1.25);
            }
        }

        // Default fallback using Levenshtein algorithm if no valid weight is extracted
        Log::info('No valid weight found, using fallback Levenshtein algorithm');
        $item_weight = BaseUtil::applyLevenshteinAlgo($categories);
        if ($item_weight !== null) {
            $this->weight_source = 'WEIGHT_MACHINE';
            $this->weight_machine_notes = 'LEVENSHTEIN';
        }
        return $this->formatWeight($item_weight);
    }

    // Helper method to format the weight
    private function formatWeight($weight)
    {
        Log::info('Formatting weight: ' . $weight);
        $item_weight = $weight * 1.25; // Apply contingency weight
        return number_format($item_weight, 2);
    }

    public function calculatePrice($price_aed, $shipping_price_aed, $weight)
    {
        // Log::info('PRICE WAS:');
        // Log::info($price_aed);
        if ($price_aed === null) {
            $price_aed = 0;
        }
        if ($shipping_price_aed === null) {
            $shipping_price_aed = 0;
        }
        if ($weight === null) {
            $weight = 0.1;
        }
        $weight_calculation = $weight * 1 * (config('app.shipping_price_aed_per_kg') / config('app.aed_rate_to_usd'));
        $price_with_comm = ($price_aed + config('app.fixed_commission_per_item_aed')) / config('app.aed_rate_to_usd');
        $shipping_price_usd = $shipping_price_aed / config('app.aed_rate_to_usd');

        $overall_commission_to_use = $price_aed >= env('DISCOUNT_ELIGIBLE_AED_PRICE') ? config('app.overall_commission_percentage') : config('app.overall_commission_percentage');
        $total_price = ($weight_calculation + $price_with_comm + $shipping_price_usd) * (1 + ($overall_commission_to_use / 100));

        return number_format($total_price, 2);
    }

    public function view(string $asin)
    {
        $url = config('app.amazon_ae_prefix') . $asin;

        return $this->scrape($url);
    }

    public function pullSiteData($url)
    {
        // Prepare data
        $payload = [
            "source" => "amazon",
            "domain" => "ae",
            "url" => $url,
            "parse" => true,
        ];

        $response = Http::withHeaders([
            "Authorization" => "Basic " . config('app.oxylabs_token'),
            "Content-Type" => "application/json",
        ])->post(config('app.oxylabs_endpoint'), $payload);

        // Check if the request was successful and a specific key exists in the response
        if ($response->successful() && isset($response['results'][0]['content'])) {
            $content = $response['results'][0]['content'];
            // Generate a unique key for this payload
            $key = config('app.amazon_ae_cache_prefix') . $this->extractASIN($url);

            // Store the content in Cache and adjust the duration as needed.
            return Cache::put($key, $content, now()->addMinutes(config('app.cache_time')));
        } else {
            // Handle the error or unsuccessful response accordingly
            return false;
        }
    }

    public function retrieveScrapedDataFromCache(string $identifier)
    {
        return Cache::get($identifier, false);
    }

    public function uploadDataToShop($data, $uuid = null, $quantity = 1, $groupForOrder = false): mixed
    {

        $data['payload']['images'] =  array($data['payload']['images'][0]);

        $logged_item = AmazonAeItem::where('asin', $data['asin'])->first();

        if ($logged_item) {

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
                    'link'   =>  $logged_item->woocommerce_link
                ]
            ];
        }

        $uploaded_product = BaseUtil::postItemToWoocommerce($data['payload']);


        $uploaded_product_ = json_decode($uploaded_product);

        if ($uploaded_product_ && isset($uploaded_product_->permalink)) {


            //log to amazon_item
            $ai = new AmazonAeItem();
            $ai->site_link = $data['url'];
            $ai->url_hash = $data['url'];
            $ai->woocommerce_product_id = $uploaded_product_->id;
            $ai->asin = $data['asin'];
            $ai->woocommerce_link = $uploaded_product_->permalink;
            $ai->payload = json_encode(json_decode($data['session'], true));
            $ai->file_id = $data['asin'];

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
                    'message'   =>  'Failed to upload item',
                    'data'    =>  $ai->errors
                ];
            }
        } else {

            return [
                'success'   =>  false,
                'message'   =>  'Failed to upload item',
                'data'    =>  $uploaded_product_
            ];
        }
    }

    public static function isSiteUrl(string $url): bool
    {
        $expectedPrefix = config('app.amazon_ae_url');

        $result = explode($expectedPrefix, $url);
        return count($result) === 2;
    }


    /***************************************/
    /** custom methods **/
    /***************************************/
    private function extractASIN($url)
    {
        $pattern = "/\/(dp|d)\/([A-Z0-9]{10})/";
        preg_match($pattern, $url, $matches);

        if (!empty($matches) && count($matches) > 2) {
            return $matches[2]; // The ASIN is in the third element of the matches array
        } else {
            return null; // Return null if no ASIN is found
        }
    }
}
