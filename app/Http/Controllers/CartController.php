<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $cart = Cart::with([
            'items.product',
            'items.variant'
        ])->firstOrCreate([
                    'user_id' => $request->user()->id
                ]);

        return response()->json([
            'cart' => $cart
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = Cart::firstOrCreate([
            'user_id' => $request->user()->id
        ]);

        $variant = ProductVariant::findOrFail(
            $validated['product_variant_id']
        );

        if ($validated['quantity'] > $variant->stock) {
            return response()->json([
                'message' => 'Not enough stock available.'
            ], 422);
        }

        $item = $cart->items()
            ->where('product_variant_id', $variant->id)
            ->first();

        if ($item) {
            $newQuantity = $item->quantity + $validated['quantity'];

            if ($newQuantity > $variant->stock) {
                return response()->json([
                    'message' => 'Not enough stock available.'
                ], 422);
            }

            $item->update([
                'quantity' => $newQuantity
            ]);
        } else {
            $item = $cart->items()->create([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'quantity' => $validated['quantity'],
            ]);
        }

        return response()->json([
            'message' => 'Product added to cart.',
            'item' => $item->load([
                'product',
                'variant'
            ])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $itemId)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = Cart::where('user_id', $request->user()->id)
            ->firstOrFail();

        $item = $cart->items()
            ->with('variant')
            ->findOrFail($itemId);

        if ($validated['quantity'] > $item->variant->stock) {
            return response()->json([
                'message' => 'Not enough stock available.'
            ], 422);
        }

        $item->update([
            'quantity' => $validated['quantity']
        ]);

        return response()->json([
            'message' => 'Cart updated successfully.',
            'item' => $item->fresh()->load([
                'product',
                'variant'
            ])
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $itemId)
    {
        $cart = Cart::where('user_id', $request->user()->id)
            ->firstOrFail();

        $item = $cart->items()->findOrFail($itemId);

        $item->delete();

        return response()->json([
            'message' => 'Item removed from cart.'
        ], 200);
    }

    /**
     * Clear the cart
     */
    public function clear(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)
            ->firstOrFail();

        $cart->items()->delete();

        return response()->json([
            'message' => 'Cart cleared successfully.',
        ], 200);
    }
}
