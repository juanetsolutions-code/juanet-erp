<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Marketplace\Models\Cart;
use App\Domain\Marketplace\Models\Order;
use App\Domain\Marketplace\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use App\Domain\Marketplace\Events\OrderCreated;
use Illuminate\Contracts\Events\Dispatcher;

class MarketplaceCheckoutService
{
    public function __construct(private Dispatcher $eventBus) {}

    public function checkout(int $cartId, array $billingDetails): Order
    {
        return DB::transaction(function () use ($cartId, $billingDetails) {
            $cart = Cart::with('items')->findOrFail($cartId);
            
            $order = Order::create([
                'user_id' => $cart->user_id,
                'total' => $cart->items->sum('price'),
                'status' => 'pending',
                'currency' => $cart->currency,
                'payment_status' => 'awaiting',
                'billing_details' => $billingDetails
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'license_type' => $item->license_type,
                    'price' => $item->price
                ]);
            }
            
            $cart->items()->delete();
            $cart->delete();

            $this->eventBus->dispatch(new OrderCreated($order->id));
            
            return $order;
        });
    }
}
