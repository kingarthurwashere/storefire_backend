<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AliExpressScraperService;
use App\Services\AmazonAeScraperService;
use App\Services\NoonScraperService;
use App\Services\SheinScraperService;
use Illuminate\Http\Request;

class ViewController extends Controller
{
    public function __construct(
        protected AliExpressScraperService $aliExpressScraperService,
        protected AmazonAeScraperService $amazonAeScraperService,
        protected NoonScraperService $noonScraperService,
        protected SheinScraperService $sheinScraperService
    ) {
    }

    public function __invoke(string $identifier, Request $request)
    {
        // Check if the request data contains a 'source' parameter
        if ($request->has('source')) {
            $source = $request->input('source');

            // Use AliExpressScraperService for AliExpress view
            if ($source === 'aliexpress') {
                return $this->aliExpressScraperService->view($identifier);
            }
            // Use AmazonAeScraperService for Amazon AE view
            elseif ($source === 'amazon_ae') {
                return $this->amazonAeScraperService->view($identifier);
            }

            // Use NoonScraperService for Noon view
            elseif ($source === 'noon') {
                return $this->noonScraperService->view($identifier);
            }

            // Use SheinScraperService for Shein view
            elseif ($source === 'shein') {
                return $this->sheinScraperService->view($identifier);
            }
        }

        // If 'source' parameter is missing or unrecognized, return appropriate fallback message
        return response()->json(['error' => 'Invalid or missing source parameter'], 400);
    }
}
