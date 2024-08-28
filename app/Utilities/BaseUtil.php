<?php

namespace App\Utilities;

use App\Services\AmazonAeScraperService;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class BaseUtil
{
    private $client;
    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => config('app.woocommerce_url'),
            'headers' => [
                'Authorization' => 'Basic ' . config('app.woocommerce_api_token'),
                'Content-Type' => 'application/json',
            ],
        ]);
    }
    /**
     * Reconstructs site url to ensure validity
     *
     * @param mixed $url The url to reconstruct.
     * @return bool Returns newly constructed url.
     */
    public static function reconstructSiteUrl(string $url): string
    {
        $parsedUrl = parse_url($url);

        if (isset($parsedUrl['scheme'])) {
            $newUrl = $parsedUrl['scheme'] . '://';
        } else {
            $newUrl = '';
        }

        if (isset($parsedUrl['host'])) {
            $newUrl .= $parsedUrl['host'];
        }

        if (isset($parsedUrl['path'])) {
            $newUrl .= $parsedUrl['path'];
        }

        return $newUrl;
    }

    public static function extractWeightInKilograms($inputString)
    {
        // Define the weight unit pattern
        $unitPattern = '/(\d+(\.\d+)?)\s*(Grams|grams|Ounces|ounces|Kilograms|kilograms|kg|g)/';

        // Extract the weight value and unit from the string
        preg_match($unitPattern, $inputString, $matches);

        if (isset($matches[1]) && isset($matches[3])) {
            // Convert the weight to kilograms based on the unit
            $weight = floatval($matches[1]);
            $unit = strtolower($matches[3]);
            $conversionFactor = 1.0; // Default conversion factor for grams

            // Apply conversion factors for different units
            switch ($unit) {
                case 'grams':
                    $conversionFactor = 0.001;
                    break;
                case 'g':
                    $conversionFactor = 0.001;
                    break;
                case 'ounces':
                    $conversionFactor = 0.0283495;
                    break;
                case 'kilograms':
                    $conversionFactor = 1.0;
                    break;
                case 'kg':
                    $conversionFactor = 1.0;
                    break;
                    // Add more cases for other units as needed

                default:
                    return null; // Invalid or unsupported unit
            }

            $kilograms = $weight * $conversionFactor;
            return $kilograms;
        }

        return null;
    }
    /**
     * Posts a new item to WooCommerce by sending a payload to the WooCommerce API.
     *
     * @param array $payload The data to be sent to WooCommerce, structured according to the API's expectations for creating a new product.
     * @return string The API response as a string. This might include a success message, ID of the created product, or an error message.
     */
    public static function postItemToWoocommerce($payload)
    {
        Log::debug('Posting item to WooCommerce', ['payload' => $payload]); // Laravel logging

        try {
            $client = new Client([
                'base_uri' => config('app.woocommerce_url'),
                'headers' => [
                    'Authorization' => 'Basic ' . config('app.woocommerce_api_token'),
                    'Content-Type' => 'application/json',
                ],
            ]);

            Log::info('Prepared HTTP client for WooCommerce API.');

            $response = $client->post("wp-json/wc/v3/products", [
                'body' => json_encode($payload),
            ]);

            $responseBody = $response->getBody()->getContents();
            Log::debug('Received response from WooCommerce API', ['response' => $responseBody]);

            return $responseBody;
        } catch (GuzzleException $e) {
            Log::error('Error posting to WooCommerce');
            Log::error(['error' => $e->getMessage(), 'payload' => $payload]);
            return $e->getMessage();
        }
    }

    public static function postOrderToWoocommerce($payload)
    {
        Log::debug('Posting order to WooCommerce', ['payload' => $payload]); // Laravel logging

        try {
            $client = new Client([
                'base_uri' => config('app.woocommerce_url'),
                'headers' => [
                    'Authorization' => 'Basic ' . config('app.woocommerce_api_token'),
                    'Content-Type' => 'application/json',
                ],
            ]);

            Log::info('Prepared HTTP client for WooCommerce API.');

            $response = $client->post("wp-json/wc/v3/orders", [
                'body' => json_encode($payload),
            ]);

            $responseBody = $response->getBody()->getContents();
            Log::debug('Received response from WooCommerce API', ['response' => $responseBody]);

            $created_order = json_decode($responseBody, true);
            return [
                "order_id" => $created_order['id'],
                "total" => $created_order['total'],
                "balance" => $created_order['total'],
                "line_items" => $created_order['line_items'],
            ];
        } catch (GuzzleException $e) {
            Log::error('Error posting ORDER to WooCommerce', ['error' => $e->getMessage()]);
            return $e->getMessage();
        }
    }

    /**
     * Updates an existing item in WooCommerce by sending updated data to the WooCommerce API for a specific product ID.
     *
     * @param int|string $product_id The ID of the product to update. This can be an integer or a string depending on the ID format.
     * @param array $payload The updated data for the product, structured according to the API's expectations for updating a product.
     * @return string The API response as a string. This might include a success message, details of the updated product, or an error message.
     */
    public static function updateItemInWoocommerce($product_id, $payload)
    {
        try {
            $client = new Client([
                'base_uri' => config('app.woocommerce_url'),
                'headers' => [
                    'Authorization' => 'Basic ' . config('app.woocommerce_api_token'),
                    'Content-Type' => 'application/json',
                ],
            ]);
            $response = $client->request('PATCH', "wp-json/wc/v3/products/{$product_id}", [
                'body' => json_encode($payload),
            ]);

            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Convert validation errors to a human-readable string.
     *
     * @param MessageBag $errors
     * @return string
     */
    public static function humanReadableErrors(MessageBag $errors): string
    {
        $output = '';

        foreach ($errors->all() as $message) {
            $output .= $message . ' ';
        }

        return rtrim($output);
    }

    public static function postTitleToEndpoint($title): ?float
    {
        $endpoint = config('app.endpoint_to_post_title');
        $auth = base64_encode(config('app.basic_auth_username') . ':' . config('app.basic_auth_password'));

        $client = new Client([
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Content-Type' => 'application/json',
            ],
        ]);

        $response = $client->post($endpoint, [
            'json' => [
                'title' => $title
            ]
        ]);

        if ($response->getStatusCode() === 200) {
            $responseData = $response->getBody()->getContents();
            return self::extractWeight($responseData);
        } else {
            return null;
        }
    }

    private static function extractWeight(string $responseData): ?float
    {
        // Decode JSON string into associative array
        $decodedResponse = json_decode($responseData, true);

        // Check if decoding was successful
        if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
            // Log raw response data along with the error message
            Log::error('Failed to decode the response data. Error: ' . json_last_error_msg() . '. Raw response: ' . $responseData);
            return null;
        } elseif (!isset($decodedResponse['converted_weight'])) {
            // Handle missing or invalid data
            Log::error('No converted_weight found in the response data');
            return null;
        }

        // Access data from the decoded response
        $convertedWeight = $decodedResponse['converted_weight'];

        // Log converted weight information
        if (isset($convertedWeight['kg']) && isset($convertedWeight['lb'])) {
            Log::info('Weight in kilograms: ' . $convertedWeight['kg']);
            Log::info('Weight in pounds: ' . $convertedWeight['lb']);
        } else {
            Log::warning('Weight data incomplete or invalid');
        }
        // Access other information as needed...

        // Return weight in kilograms
        return isset($convertedWeight['kg']) ? (float)$convertedWeight['kg'] : null;
    }

    public static function searchAmazonForSimilarItem($title)
    {
        //exclude item sizes from algorithm
        $itemsToRemove = ["inches", "inch", "in."];
        $title = str_replace($itemsToRemove, "", $title);

        $client = new Client();
        $url = 'https://realtime.oxylabs.io/v1/queries';

        // Your credentials
        $username = env('OXYLABS_USERNAME');
        $password = env('OXYLABS_PASSWORD');

        try {
            $response = $client->request('POST', $url, [
                'auth' => [$username, $password],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'source' => 'amazon_search',
                    'query' => $title,
                    'domain' => 'ae',
                    'parse' => true,
                ],
                'http_errors' => false, // For handling HTTP errors gracefully
            ]);

            // Get the status code and body of the response
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            //Preferring paid items because they generally always include shipping price
            $decoded = json_decode($body, true)['results'][0]['content']['results']['paid'];
            if (count($decoded) > 0) {
                $resp_data = $decoded;
            } else {
                $resp_data = json_decode($body, true)['results'][0]['content']['results']['organic'];
            }


            // Log the status code and response body

            if (count($resp_data) > 0) {

                // Log::info($resp_data[0]['asin']);
                $amazonAeService = new AmazonAeScraperService();
                $asin  = $resp_data[0]['asin'];
                Log::info("ITEM FOR SCRAPE:");
                Log::info($asin);
                $constructed_url = "https://www.amazon.ae/dp/{$asin}";

                //scrap item
                //return SELF::scrapeAmazonByAsin($resp_data[0]['asin']);
                $resp =  $amazonAeService->attemptScrape($constructed_url);


                return (float)$resp['data']['item']['item_weight'];
            }
        } catch (RequestException $e) {
            Log::error("HTTP Request failed: " . $e->getMessage());

            // Handle the case where the server is unreachable or gives an error
            if ($e->hasResponse()) {
                $errorMessage = $e->getResponse()->getBody()->getContents();
                Log::error("Error Response Body: $errorMessage");
            }

            return response()->json(['error' => 'Request failed'], 500);
        }
    }

    public static function scrapeAmazonByAsin($asin)
    {
        $client = new Client();
        $url = 'https://realtime.oxylabs.io/v1/queries';

        // Your credentials
        $username = env('OXYLABS_USERNAME');
        $password = env('OXYLABS_PASSWORD');

        try {
            $response = $client->request('POST', $url, [
                'auth' => [$username, $password],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'source' => 'amazon',
                    'url' => "https://www.amazon.ae/dp/{$asin}",
                    'domain' => 'ae',
                    'parse' => true,
                ],
                'http_errors' => false, // For handling HTTP errors gracefully
            ]);

            // Get the status code and body of the response
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            $resp_data = json_decode($body, true)['results'][0]['content'];
            // Log the status code and response body
            Log::info("SCRAPED ITEM:");
            Log::info($resp_data);


            return $resp_data;
        } catch (RequestException $e) {
            Log::error("HTTP Request failed: " . $e->getMessage());

            // Handle the case where the server is unreachable or gives an error
            if ($e->hasResponse()) {
                $errorMessage = $e->getResponse()->getBody()->getContents();
                Log::error("Error Response Body: $errorMessage");
            }

            return response()->json(['error' => 'Request failed'], 500);
        }
    }

    public static function applyLevenshteinAlgo($searchTerms)
    {
        $items = [
            "Bag" => 770, "Nail polish" => 130, "Belts / Chains" => 200,
            "Nail art products" => 130, "Bracelet" => 40, "Necklace" => 60,
            "Capacitor / Resistor" => 30, "Pants" => 330, "Cellphone case" => 80,
            "Plush cloth art toys" => 410, "Cosplay" => 340, "Purse" => 210,
            "Diode / LED" => 20, "Ring" => 20, "Doll" => 110,
            "Scarf" => 190, "Dress" => 380, "Shirt" => 280, "Earring" => 20,
            "Suits" => 530, "Football suit" => 350, "Sunglass" => 180,
            "Hair" => 30, "T-Shirt" => 300, "Hat" => 130,
            "Loafer flats" => 1100,
            "Road Running Shoes" => 598,
            "shoes" => 500,
            "Thong Sandals" => 250,
            "Laptop" => 2700,
            "Notebook" => 2700,
            "Underwear" => 80, "IC integrated circuit" => 10, "Watch" => 340, "television" => 20000, "Smart TV" => 20000,
            "Low Shoes" => 1100, "Wig" => 80, "Phone Case" => 80, "Cases & Covers" => 80, "Basic Cases" => 80
        ];


        $closestMatch = SELF::findSingleClosestMatch($items, $searchTerms);

        if ($closestMatch) {
            $item = key($closestMatch);
            $weight = $closestMatch[$item];
            Log::info("Closest match: $item with weight $weight g.");
            return (float)($weight / 1000);
        } else {
            //default shipping weight in kg
            return 0.2;
        }
    }

    public static function findSingleClosestMatch($items, $searchTerms)
    {
        $bestMatch = null;
        $smallestDistance = PHP_INT_MAX;
        $bestMatchItem = '';

        foreach ($searchTerms as $term) {
            foreach ($items as $item => $weight) {
                $distance = levenshtein(strtolower($term), strtolower($item));
                if ($distance < $smallestDistance) {
                    $smallestDistance = $distance;
                    $bestMatch = $item;
                    $bestMatchWeight = $weight;
                }
            }
        }

        return $bestMatch ? [$bestMatch => $bestMatchWeight] : null;
    }

    /**
     * Clean the given URL by removing unnecessary backslashes and decoding URL-encoded characters.
     *
     * @param string $url The URL to be cleaned.
     * @return string The cleaned URL.
     */
    public static function cleanUrl(string $url): string
    {
        // Remove unnecessary backslashes
        $cleanedUrl = stripslashes($url);

        // Decode URL-encoded characters
        $cleanedUrl = urldecode($cleanedUrl);

        return $cleanedUrl;
    }

    public static function formatPrice($price): float
    {
        return (float)str_replace(',', '', $price);
    }
}
