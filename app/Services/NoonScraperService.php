<?php

namespace App\Services;

use App\Models\WeightInformation;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log; // Add Log facade
use App\Interfaces\EcommerceScraperInterface;
use App\Models\NoonItem;
use App\Utilities\BaseUtil;

class NoonScraperService implements EcommerceScraperInterface
{
    protected $client;
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
    }

    public function attemptScrape(string $url)
    {
        $given_url = BaseUtil::reconstructSiteUrl($url);

        $scrape_resp = $this->scrape($given_url);

        if($scrape_resp['success']) {
            //log
            try {
                WeightInformation::create([
                    'product_name' => $scrape_resp['data']['item']['title'],
                    'determined_weight' => $scrape_resp['data']['item']['item_weight'],
                    'store' => 'NOON',
                    'url' => $scrape_resp['data']['item']['url'],
                    'weight_source' => $this->weight_source,
                    'weight_machine_notes' => $this->weight_source !== 'STORE' ? $this->weight_machine_notes : null,
                    'payload' => json_encode($scrape_resp['data']['item'])
                ]);
            } catch (\Throwable $th) {
                //throw $th;
            }
        }

        return $scrape_resp;
    }

    public function retrieveUploadedProduct($identifier)
    {
        $item = NoonItem::where('file_id', $identifier)->first();
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

        // Extract ids from the URL
        $asin = $this->extractIds($url);

        // Check if the item exists in the database
        $item = NoonItem::where('url', $asin)->first();

        if ($item) {
            // If the item exists in the database, retrieve its weight and calculate the price
            $item_weight = $this->determineWeight($item->payload);
            $price = $this->calculatePrice($item->payload['price'], $item->payload['shipping_price'], $item_weight);

            return [
                'success' => true,
                'message' => 'Not Verified',
                'data' => [
                    'identifier' => '',
                    'item' => array_merge($item->payload, [
                        'dxb_price' => $price,
                        'asin' => $asin,
                        'item_weight' => $item_weight,
                        'scraped_url' => $url
                    ])
                ]
            ];
        } else {
            // If the item is not in the database, check if it's in the cache
            $cache_key = config('app.noon_cache_prefix') . $asin;
            $is_cached = Cache::has($cache_key);

            if ($is_cached) {
                // If the item is in the cache, retrieve it and calculate the price
                $retrieved_data = $this->retrieveScrapedDataFromCache($cache_key);
                $item_weight = $this->determineWeight($retrieved_data);

                $price = $this->calculatePrice($retrieved_data['price'], $retrieved_data['shipping_price'], $item_weight);

                return [
                    'success' => true,
                    'message' => 'Data retrieved successfully',
                    'data' => [
                        'identifier' => $asin,
                        'item' => array_merge($retrieved_data, [
                            'dxb_price' => $price,
                            'asin' => $asin,
                            'item_weight' => $item_weight,
                            'scraped_url' => $url
                        ])
                    ]
                ];
            } else {
                // If the item is not in the cache, attempt to scrape it from the given URL
                $ids_url = config('app.noon_prefix') . $asin;
                $this->pullSiteData($ids_url);

                // Check again if the item is now in the cache
                $is_cached = Cache::has($cache_key);

                if (!$is_cached) {
                    // If the item is still not in the cache, return failure
                    return [
                        'success' => false,
                        'message' => 'Failed to read data',
                        'data' => []
                    ];
                } else {
                    // If the item is now in the cache, retrieve it and calculate the price
                    $retrieved_data = $this->retrieveScrapedDataFromCache($cache_key);
                    $item_weight = $this->determineWeight($retrieved_data);

                    $price = $this->calculatePrice($retrieved_data['price'], $retrieved_data['shipping_price'], $item_weight);

                    return [
                        'success' => true,
                        'message' => 'Data read successfully',
                        'data' => [
                            'identifier' => $asin,
                            'item' => array_merge($retrieved_data, [
                                'dxb_price' => $price,
                                'asin' => $asin,
                                'item_weight' => $item_weight,
                                'scraped_url' => $url
                            ])
                        ]
                    ];
                }
            }
        }
    }

    public function determineWeight($payload)
    {
        // Initialize default weight
        $item_weight = 2; // Default weight in kilograms if no dimensions are provided

        // Check if 'title' key exists and then proceed
        if (isset($payload['title'])) {
            $title = $payload['title'];

            // Pass the title to BaseUtil::postTitleToEndpoint() to get the item weight
            $item_weight = BaseUtil::searchAmazonForSimilarItem($title);
            if($item_weight !== null) {
                $this->weight_source = 'WEIGHT_MACHINE';
                $this->weight_machine_notes = 'AMAZON';
            }
        }

        return number_format($item_weight, 2);
    }

    public function calculatePrice($price_aed, $shipping_price_aed, $weight)
    {
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

    public function view(string $identifier)
    {
        Log::info('View Function: ' . $identifier);
        $url = config('app.noon_prefix') . $identifier . '/p/';

        return $this->scrape($url);
    }

    public function pullSiteData($url)
    {

        // Prepare data
        $payload = [
            "url" => $url,
            "platform" => "noon"
        ];

        try {
            $response = Http::withHeaders([
                "Content-Type" => "application/json",
                "Location" => "United Arab Emirates"
            ])
                ->timeout(120) // Set timeout to 60 seconds
                ->post(config('app.scrape_api_endpoint'), $payload);

            // Check if the request was successful and the necessary keys exist in the response
            if ($response->successful() && isset($response['url'])) {
                // Extract the required data from the response
                $url = $response['url'];
                $content = $response->json();

                // Generate a unique key for this payload
                $key = config('app.noon_cache_prefix') . $this->extractIds($url);

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
            // Log any exceptions or errors

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

    public function uploadDataToShop($data): mixed
    {
        Log::info("Starting uploadDataToShop function...");

        Log::info('UPLOAD FULL PAYLOAD');
        Log::info($data);


        $data['payload'] =  array($data['payload']);

        //Check if already uploaded
        $logged_item = NoonItem::where(['file_id' => $data['asin']])->first();

        if ($logged_item) {
            BaseUtil::updateItemInWoocommerce($logged_item->woocommerce_product_id, [
                'sale_price' => null,
                'regular_price' => $data['payload'][0]['regular_price']
            ]);

            return [
                'success'   =>  true,
                'message'   =>  'Item uploaded',
                'data'    =>  [
                    'link'   =>  $logged_item->woocommerce_link
                ]
            ];
        }

        Log::info('NOON UPLOAD PAYLOAD');
        Log::info($data['payload']);
        Log::info('NOON UPLOAD PAYLOAD ACTUAL');
        Log::info($data['payload'][0]);

        $uploaded_product = BaseUtil::postItemToWoocommerce($data['payload'][0]);

        $uploaded_product_ = json_decode($uploaded_product);

        Log::info('NOON WOOCOMMERCE RESPONSE');
        Log::info((array)$uploaded_product_);

        if ($uploaded_product_ && isset($uploaded_product_->permalink)) {

            //log to Noon_item
            $ai = new NoonItem();
            $ai->site_link = $data['url'];
            $ai->url_hash = $data['url'];
            $ai->woocommerce_product_id = $uploaded_product_->id;
            $ai->url = $data['url'];
            $ai->woocommerce_link = $uploaded_product_->permalink;
            $ai->payload = json_encode(json_decode($data['session'], true));
            $ai->file_id = $data['asin'];

            if ($ai->save()) {
                return [
                    'success'   =>  true,
                    'message'   =>  'Item uploaded',
                    'data'    =>  [
                        'link'   =>  $ai->woocommerce_link
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
        $expectedPrefix = config('app.noon_url');

        $result = explode($expectedPrefix, $url);
        return count($result) === 2;
    }

    /***************************************/
    /** custom methods **/
    /***************************************/

    private function extractIds($url)
    {
        // Use regular expression to match the pattern "/N followed by any alphanumeric characters, followed by /p/"
        preg_match('/\/[A-Z0-9]+\/p\//', $url, $matches);

        // If a match is found, return it after removing the first slash
        if (!empty($matches)) {
            $asin = $matches[0];
            // Remove the first slash
            $asin = substr($asin, 1);
            return $asin;
        }

        // If no match is found, return null or handle the case as needed
        return null;
    }
}
