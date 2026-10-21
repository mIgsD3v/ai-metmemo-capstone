<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the AI service may call /internal routes: it sends the shared secret as X-Internal-Token.
 * Unlike the AI side, an unset token refuses everything, so a missing env var never opens the API.
 */
class VerifyInternalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.ai.token');
        $given = (string) $request->header('X-Internal-Token');

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid internal token');
        }

        return $next($request);
    }
}
