<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();
        
        $request->headers->set('X-Request-Id', $requestId);
        
        $response = $next($request);
        
        $response->headers->set('X-Request-Id', $requestId);
        
        return $response;
    }
}
