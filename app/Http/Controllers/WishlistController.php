<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index()
    {
        return view('marketplace_wishlist');
    }

    public function add(Request $request)
    {
        // ...
        return response()->json(['success' => true]);
    }

    public function remove(Request $request)
    {
        // ...
        return response()->json(['success' => true]);
    }

    public function moveToCart(Request $request)
    {
        // ...
        return response()->json(['success' => true]);
    }
}
