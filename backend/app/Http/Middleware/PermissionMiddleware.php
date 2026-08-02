<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        if (!$request->user() || !collect($permissions)->contains(fn ($permission) => $request->user()->hasPermission($permission))) {
            return response()->json(['message'=>'Anda tidak memiliki izin untuk fitur ini.'], 403);
        }
        return $next($request);
    }
}
