<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an id, echoes it back as X-Request-Id, and attaches it to every log line,
 * so one customer report can be traced through web, queue and webhook logs.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        // Accept an id from a trusted proxy/load balancer, but only if it is a safe short token.
        $incoming = (string) $request->header('X-Request-Id', '');
        $id = preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) ? $incoming : (string) Str::uuid();

        $request->attributes->set('request_id', $id);
        Log::shareContext(['request_id' => $id]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
