<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $orders = Order::with([
            'items.product',
            'items.variant'
        ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'orders' => $orders
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_method' => [
                'required',
                'in:COD,GCASH,MAYA,CARD'
            ],
        ]);

        $user = $request->user();

        $cart = Cart::with([
            'items.product',
            'items.variant'
        ])
            ->where('user_id', $user->id)
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty.'
            ], 422);
        }

        $order = DB::transaction(function () use ($cart, $validated, $user) {

            $total = 0;

            foreach ($cart->items as $item) {

                $variant = $item->variant;

                if ($item->quantity > $variant->stock) {
                    abort(422, "Not enough stock for {$item->product->name}.");
                }

                $total += $item->product->price * $item->quantity;
            }

            $order = Order::create([
                'user_id' => $user->id,
                'total_amount' => $total,
                'payment_method' => $validated['payment_method'],
                'status' => 'PENDING',
            ]);

            foreach ($cart->items as $item) {

                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,

                    // Save the current price
                    // for historical accuracy.
                    'price' => $item->product->price,
                ]);

                $item->variant->decrement(
                    'stock',
                    $item->quantity
                );
            }

            $cart->items()->delete();

            return $order;
        });

        return response()->json([
            'message' => 'Order placed successfully.',
            'order' => $order->load([
                'items.product',
                'items.variant'
            ])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $orderId)
    {
        $order = Order::with([
            'items.product',
            'items.variant'
        ])
            ->where('user_id', $request->user()->id)
            ->findOrFail($orderId);

        return response()->json([
            'order' => $order
        ], 200);
    }

    /**
     * Cancel the order.
     */
    public function cancel(Request $request, $orderId)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->findOrFail($orderId);

        if ($order->status !== 'PENDING') {
            return response()->json([
                'message' => 'This order can no longer be cancelled.'
            ], 422);
        }

        DB::transaction(function () use ($order) {

            foreach ($order->items as $item) {
                $item->variant->increment(
                    'stock',
                    $item->quantity
                );
            }

            $order->update([
                'status' => 'CANCELLED'
            ]);
        });

        return response()->json([
            'message' => 'Order cancelled successfully.'
        ], 200);
    }
}
