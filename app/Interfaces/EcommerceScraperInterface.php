<?php

namespace App\Interfaces;

interface EcommerceScraperInterface
{
    /**
     * Attempts to perform the scraping of the e-commerce site.
     *
     * @param string $url The URL of the e-commerce site to scrape.
     * @return mixed Scraped data or false if scraping fails.
     */
    public function attemptScrape(string $url);

    /**
     * Retrieves scraped data from the cache.
     *
     * @param string $identifier Unique identifier for the scrape request.
     * @return mixed Returns the cached data if available, false otherwise.
     */
    public function retrieveScrapedDataFromCache(string $identifier);

    /**
     * Checks if the item being scraped was uploaded before
     *
     * @param mixed $identifier For the data to be retrieved.
     * @return mixed Returns $productData on success, null on failure.
     */
    public function retrieveUploadedProduct(string $identifier);

    /**
     * Uploads the scraped data to the shop system.
     *
     * @param mixed $data The data to upload to the shop.
     * @return bool Returns true on success, false on failure.
     */
    public function uploadDataToShop($data): mixed;

    /**
     * Checks that the url is indeed valid.
     *
     * @param string $url The site url.
     * @return bool Returns true on success, false on failure.
     */
    public static function isSiteUrl(string $url);

    /**
     * Pulls the data from the currently hydrated source.
     *
     * @param mixed $irl The url of the product.
     * @return bool Returns scraped data.
     */
    public function pullSiteData(string $url);

    /**
     * Views the scraped data by identifier.
     *
     * @param string $identifier The identifier to get the cached product.
     * @return mixed Returns product data.
     */
    public function view(string $identifier);

    /**
     * Determines the weight of a product based on provided product data.
     *
     * @param mixed $product_data The product data which might contain weight information directly or details from which weight can be inferred.
     * @return mixed The determined weight of the product. The return type can vary based on how weight is represented or calculated.
     */
    public function determineWeight(mixed $product_data);

    /**
     * Calculates the total price of a product by adding its price to the shipping cost, potentially adjusted by the product's weight.
     *
     * @param float $price_aed The price of the product in AED.
     * @param float $shipping_price_aed The shipping price of the product in AED.
     * @param float $weight The weight of the product, which may affect the final price calculation.
     * @return float The total price of the product, including shipping, as affected by its weight.
     */
    public function calculatePrice($price_aed, $shipping_price_aed, $weight);
}
