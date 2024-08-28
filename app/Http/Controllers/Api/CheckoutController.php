<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class CheckoutController extends Controller
{
    public static function payment_methods()
    {
        try {
            $client = new Client([
                'base_uri' => 'https://www.dxbrunners.com',
                'headers' => [
                    'Authorization' => 'Basic Y2tfZDFmZmJiNThlY2VjZWEzOTVjOTAyOTU3MTU2ODE5MDdhM2U2MjE3ZDpjc18zZDBmODQ5MzExYWUwZWU3YzQyNTM0ODkyZWZkMzI3MmM0ZmIyMTU4',
                    'Content-Type' => 'application/json',
                ],
            ]);
            $response = $client->get("wp-json/wc/v3/payment_gateways");
            $responseBody = $response->getBody()->getContents();
            $paymentGateways = json_decode($responseBody, true);

            // Filter the payment gateways to return only those that are enabled
            $enabledGateways = array_filter($paymentGateways, function ($gateway) {
                return isset($gateway['enabled']) && $gateway['enabled'] === true && $gateway['title'] !== '';
            });

            // Re-index array to remove keys
            $enabledGateways = array_values($enabledGateways);

            return response()->json($enabledGateways);
        } catch (GuzzleException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
