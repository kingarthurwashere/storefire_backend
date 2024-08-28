<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderPlaceRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItemAttempt;
use App\Models\Product;
use App\Utilities\BaseUtil;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __invoke()
    {
        $user = request()->user();

        // Get orders where user_id matches or billing email matches user's email
        $user_orders = Order::where('user_id', $user->id)
            ->orWhereHas('billing_details', function ($query) use ($user) {
                $query->where('email', $user->email);
            })
            ->paginate();

        return $user_orders;
    }

    //Not Authenticated
    public function place(OrderPlaceRequest $request)
    {
        return $this->handleOrderPlacement($request);
    }

    //Authenticated
    public function placeForAuthenticatedUser(OrderPlaceRequest $request)
    {
        return $this->handleOrderPlacement($request);
    }

    private function handleOrderPlacement(OrderPlaceRequest $request)
    {
        $items = OrderItemAttempt::where('uuid', $request->get('attempt_id'))->get();

        $line_items =  $items->map(function ($user) {
            return [
                'product_id' => $user['woocommerce_product_id'],
                'quantity' => $user['quantity'],
            ];
        });

        $prepared_data = array_merge(
            [
                "payment_method" => request()->get('extras')['payment_method']['id'],
                "payment_method_title" => request()->get('extras')['payment_method']['title'],
                "set_paid" => false,
                "meta_data" => [
                    [
                        "key" => "sales_agent",
                        "value" => request()->get('extras')['agent']['ID']
                    ],
                    [
                        "key" => "agent_name",
                        "value" => request()->get('extras')['agent']['display_name']
                    ],
                ]
            ],
            request()->only(['billing', 'shipping']),
            [
                'line_items' => $line_items->toArray()
            ]
        );

        $create_order = BaseUtil::postOrderToWoocommerce($prepared_data);

        //return $create_order;

        //create order record
        $attributes = [
            'woocommerce_order_id' => $create_order['order_id'],
            'total' => $create_order['total'],
            'balance' => $create_order['total'],
        ];

        if ($request->user()) {
            $attributes['user_id'] = $request->user()->id;
        }

        $new_order = Order::create($attributes);


        if ($new_order) {
            //Create products
            foreach ($create_order['line_items'] as $product) {
                try {
                    $new_product = Product::create([
                        'name' => $product['name'],
                        'woocommerce_product_id' => $product['product_id'],
                        'variation_id' => $product['variation_id'],
                        'price' => $product['price'],
                        'image' => $product['image']['src'],
                    ]);
                } catch (\Throwable $th) {
                    //ignore for now
                } finally {
                    $existing_product = Product::where('slug', Str::slug($product['name']))->first();
                    $new_order->purchased_items()->create([
                        'product_id' => $existing_product->id,
                        'quantity' => $product['quantity'],
                        'total' => $product['total'],
                    ]);
                }
            }

            //Add billing details
            $new_order->billing_details()->create(request()->only('billing')['billing']);
            //Add shipping details
            $new_order->shipping_details()->create(request()->only('shipping')['shipping']);
        }

        // Process the order
        return response()->json(['message' => 'Order placed successfully', 'data' => [
            'order' => $new_order
        ]]);
    }

    public function view(Order $order)
    {
        // Load the 'purchased_items' relationship
        $order->load('billing_details');
        $order->load('shipping_details');
        $order->load('purchased_items.product');

        // Return the order with the loaded relationship using OrderResource
        return new OrderResource($order);
    }
}
