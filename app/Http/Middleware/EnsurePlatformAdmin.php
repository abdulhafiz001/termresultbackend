<?php

namespace App\Http\Middleware;

use App\Models\PlatformAdmin;
use Closure;
use Illuminate\Http\Request;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ! ($user instanceof PlatformAdmin)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }
        if (! ($user->is_active ?? false)) {
            return response()->json(['message' => 'Account disabled.'], 403);
        }

        return $next($request);
    }
}


