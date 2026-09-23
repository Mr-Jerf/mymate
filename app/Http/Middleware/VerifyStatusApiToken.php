<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyStatusApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) env('STATUS_API_TOKEN', '');
        $provided = (string) ($request->header('X-Status-Api-Key') ?: $request->bearerToken() ?: '');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Status API authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
