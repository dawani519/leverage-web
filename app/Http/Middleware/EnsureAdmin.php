<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // 404 for non-admins, so the admin area does not advertise its existence.
        abort_unless($request->user()?->isAdmin(), 404);

        return $next($request);
    }
}
