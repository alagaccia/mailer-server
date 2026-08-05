<?php

namespace App\Http\Middleware;

use App\Support\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    /**
     * Finché l'applicazione non è installata, ogni richiesta web viene
     * reindirizzata al wizard di installazione e l'API risponde 503.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! InstallState::installed() && ! $request->is('install', 'install/*')) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['error' => 'Application not installed'], 503);
            }

            return redirect('/install');
        }

        return $next($request);
    }
}
