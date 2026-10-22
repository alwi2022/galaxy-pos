<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class AtomicBusinessWrite
{
    public function handle($request, Closure $next)
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return DB::transaction(fn () => $next($request), 3);
        }
        return $next($request);
    }
}
