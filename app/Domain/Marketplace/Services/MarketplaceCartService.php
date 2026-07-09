<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Marketplace\Models\Cart;
use App\Domain\Marketplace\Models\CartItem;
use App\Domain\Marketplace\Repositories\MarketplaceCartRepository;
use App\Domain\Marketplace\Events\ItemAdded;
use App\Domain\Marketplace\Events\ItemRemoved;
use App\Domain\Marketplace\Events\QuantityUpdated;
use App\Domain\Marketplace\Events\CartMerged;
use Illuminate\Contracts\Events\Dispatcher;

class MarketplaceCartService
{
    public function __construct(
        private MarketplaceCartRepository $repository,
        private Dispatcher $eventBus
    ) {}

    public function addProduct(int $cartId, array $data, ?string $visitorId, ?string $sessionId): CartItem
    {
        $item = $this->repository->addItem($cartId, $data);
        $this->eventBus->dispatch(new ItemAdded($cartId, $data['product_id'], $data['license_type'], $data['quantity'], $visitorId, $sessionId));
        return $item;
    }

    public function removeProduct(int $cartId, string $productId, ?string $visitorId, ?string $sessionId): void
    {
        $this->repository->removeItem($cartId, $productId);
        $this->eventBus->dispatch(new ItemRemoved($cartId, $productId, $visitorId, $sessionId));
    }

    public function mergeAnonymousCart(int $guestCartId, int $userCartId, ?string $visitorId, ?string $sessionId): void
    {
        // Simple merge logic: move items
        $guestItems = CartItem::where('cart_id', $guestCartId)->get();
        foreach ($guestItems as $item) {
            $this->repository->addItem($userCartId, [
                'product_id' => $item->product_id,
                'license_type' => $item->license_type,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ]);
            $item->delete();
        }
        $this->eventBus->dispatch(new CartMerged($guestCartId, $userCartId, $visitorId, $sessionId));
    }

    public function updateQuantity(int $cartId, string $productId, int $quantity, ?string $visitorId, ?string $sessionId): void
    {
        CartItem::where('cart_id', $cartId)->where('product_id', $productId)->update(['quantity' => $quantity]);
        $this->eventBus->dispatch(new QuantityUpdated($cartId, $productId, $quantity, $visitorId, $sessionId));
    }

    public function clearCart(int $cartId): void
    {
        CartItem::where('cart_id', $cartId)->delete();
    }

