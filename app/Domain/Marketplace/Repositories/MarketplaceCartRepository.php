<?php

namespace App\Domain\Marketplace\Repositories;

use App\Domain\Marketplace\Models\Cart;
use App\Domain\Marketplace\Models\CartItem;
use Illuminate\Support\Facades\DB;

class MarketplaceCartRepository
{
    public function findOrCreateActiveCart(?int $userId, ?string $sessionId): Cart
    {
        return Cart::firstOrCreate(
            ['session_id' => $sessionId, 'status' => 'active'],
            ['user_id' => $userId, 'currency' => 'KES']
        );
    }

    public function addItem(int $cartId, array $data): CartItem
    {
        return CartItem::updateOrCreate(
            ['cart_id' => $cartId, 'product_id' => $data['product_id'], 'license_type' => $data['license_type']],
            ['quantity' => DB::raw('quantity + ' . ($data['quantity'] ?? 1)), 'price' => $data['price']]
        );
    }

    public function removeItem(int $cartId, string $productId): void
    {
        CartItem::where('cart_id', $cartId)->where('product_id', $productId)->delete();
    }
}
