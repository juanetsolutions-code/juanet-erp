<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CompareController extends Controller
{
    public function index()
    {
        return view('marketplace_compare');
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

    public function clear(Request $request)
    {
        // ...
        return response()->json(['success' => true]);
    }
}
