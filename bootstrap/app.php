<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Branded error page instead of the plain framework one.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson()) {
                return $response;
            }

            // Expired form (419): go back and explain, instead of a dead end.
            if ($status === 419) {
                return back()
                    ->with('flash.banner', 'Your session expired. Please try again.')
                    ->with('flash.bannerStyle', 'danger');
            }

            // Keep Laravel's detailed error screen for server errors while developing.
            if ($status >= 500 && config('app.debug')) {
                return $response;
            }

            if (! in_array($status, [403, 404, 500, 503], true)) {
                return $response;
            }

            return Inertia::render('Error', [
                'status' => $status,
                // A missing public card gets its own message. Hidden and non-existent cards look the same,
                // so a hidden card's existence is never revealed.
                'context' => $request->is('c/*') ? 'card' : null,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
