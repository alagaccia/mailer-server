<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureInstalled::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
        ]);

        // Prima dell'installazione ogni richiesta deve finire sul wizard,
        // anche quelle che l'auth manderebbe al login.
        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            EnsureInstalled::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Contratto API preservato dalla vecchia app: shape JSON fisse per 405 e 500.
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request): ?Response {
            if ($request->is('api/*')) {
                return response()->json(['error' => 'Method Not Allowed. Use POST.'], 405);
            }

            return null;
        });

        $exceptions->render(function (Throwable $e, Request $request): ?Response {
            if ($request->is('api/*') && ! $e instanceof HttpExceptionInterface && ! $e instanceof ValidationException) {
                report($e);

                return response()->json([
                    'error' => 'Internal Server Error',
                    'message' => $e->getMessage(),
                ], 500);
            }

            return null;
        });
    })->create();
