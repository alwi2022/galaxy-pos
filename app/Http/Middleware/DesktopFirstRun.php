<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DesktopFirstRun
{
    public function handle(Request $request, Closure $next)
    {
        if (config('desktop.enabled') && !$request->routeIs('desktop.setup', 'desktop.setup.store') && !DB::table('users')->exists()) {
            return redirect()->route('desktop.setup');
        }
        return $next($request);
    }
}
