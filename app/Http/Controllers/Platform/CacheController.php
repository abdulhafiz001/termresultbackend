<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheController extends Controller
{
    public function clear(Request $request)
    {
        // Clears application cache store (includes traffic chart caches).
        Cache::flush();

        return response()->json(['message' => 'Cache cleared.']);
    }
}


