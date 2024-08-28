<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AliExpressScraperService;
use App\Services\AmazonAeScraperService;
use App\Services\NoonScraperService;
use App\Services\SheinScraperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UploadController extends Controller
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
                return $this->aliExpressScraperService->uploadDataToShop($request->all());
            }
            // Use AmazonAeScraperService for Amazon AE uploads
            if ($source === 'amazon_ae') {
                return $this->amazonAeScraperService->uploadDataToShop($request->all());
            }
        }

        // Return an error response if source is not provided or unrecognized
        return response()->json(['error' => 'Invalid or missing source parameter.'], 400);
    }

    public function multiple(Request $request)
    {
        // Define validation rules for query parameters
        $validator = Validator::make($request->query(), [
            'source' => 'required|string|in:aliexpress,amazon_ae',
            'uuid' => 'required',
            'quantity' => 'required|integer|min:1',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Retrieve validated query parameters
        $validated = $validator->validated();
        $source = $validated['source'];
        $uuid = $validated['uuid'];
        $quantity = $validated['quantity'];

        // Use AliExpressScraperService for AliExpress uploads
        if ($source === 'aliexpress') {
            return $this->aliExpressScraperService->uploadDataToShop($request->all(), $uuid, $quantity, true);
        }

        // Use AmazonAeScraperService for Amazon AE uploads
        if ($source === 'amazon_ae') {
            return $this->amazonAeScraperService->uploadDataToShop($request->all(), $uuid, $quantity, true);
        }

        // Return an error response if source is not provided or unrecognized
        return response()->json(['error' => 'Invalid or missing source parameter.'], 400);
    }
}
