<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AliExpressScraperService;
use App\Services\AmazonAeScraperService;
use App\Services\NoonScraperService;
use App\Services\SheinScraperService;
use Illuminate\Http\Request;

class ProductRetrievalController extends Controller
{
    public function __construct(
        protected AliExpressScraperService $aliExpressScraperService,
        protected AmazonAeScraperService $amazonAeScraperService,
        protected NoonScraperService $noonScraperService,
        protected SheinScraperService $sheinScraperService
    ) {
    }

    public function __invoke(Request $request)
    {
        // Check if the request data contains a 'source' parameter
        if ($request->has('source')) {
            $source = $request->input('source');

            // Use AliExpressScraperService for AliExpress uploads
            if ($source === 'aliexpress') {
                return $this->aliExpressScraperService->retrieveUploadedProduct($request->get('identifier'));
            }
            // Use AmazonAeScraperService for Amazon AE uploads
            elseif ($source === 'amazon_ae') {
                return $this->amazonAeScraperService->retrieveUploadedProduct($request->get('identifier'));
            }
            // Use NoonScraperService for Noon uploads
            elseif ($source === 'noon') {
                return $this->noonScraperService->retrieveUploadedProduct($request->get('identifier'));
            }
            // Use SheinScraperService for Noon uploads
            elseif ($source === 'shein') {
                return $this->sheinScraperService->retrieveUploadedProduct($request->get('identifier'));
            }
        }

        // // Return an error response if source is not provided or unrecognized
        // return response()->json(['error' => 'Invalid or missing source parameter.'], 400);
    }
}
