<?php

namespace App\Http\Controllers;

use App\Domain\Marketplace\Services\MarketplaceCartService;
use App\Domain\Marketplace\Repositories\MarketplaceCartRepository;
use Illuminate\Http\Request;

class MarketplaceCartController extends Controller
{
    public function __construct(
        private MarketplaceCartService $cartService,
        private MarketplaceCartRepository $cartRepo
    ) {}

    public function index()
    {
        return view('marketplace_cart');
    }

    public function getCart(Request $request)
    {
        $cart = $this->cartRepo->findOrCreateActiveCart(auth()->id(), $request->cookie('juanet_session_id'));
        return response()->json($cart->load('items'));
    }

    public function add(Request $request)
    {
        $cart = $this->cartRepo->findOrCreateActiveCart(auth()->id(), $request->cookie('juanet_session_id'));
        $this->cartService->addProduct($cart->id, $request->only(['product_id', 'license_type', 'quantity', 'price']), $request->cookie('juanet_visitor_id'), $request->cookie('juanet_session_id'));
        return response()->json(['success' => true]);
    }

    public function update(Request $request)
    {
        $cart = $this->cartRepo->findOrCreateActiveCart(auth()->id(), $request->cookie('juanet_session_id'));
        $this->cartService->updateQuantity($cart->id, $request->product_id, $request->quantity, $request->cookie('juanet_visitor_id'), $request->cookie('juanet_session_id'));
        return response()->json(['success' => true]);
    }

    public function remove(Request $request)
    {
        $cart = $this->cartRepo->findOrCreateActiveCart(auth()->id(), $request->cookie('juanet_session_id'));
        $this->cartService->removeProduct($cart->id, $request->product_id, $request->cookie('juanet_visitor_id'), $request->cookie('juanet_session_id'));
        return response()->json(['success' => true]);
    }

    public function clear(Request $request)
    {
        $cart = $this->cartRepo->findOrCreateActiveCart(auth()->id(), $request->cookie('juanet_session_id'));
        $this->cartService->clearCart($cart->id);
        return response()->json(['success' => true]);
    }
}
