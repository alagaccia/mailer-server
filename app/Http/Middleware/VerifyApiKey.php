<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    /**
     * Autentica la richiesta tramite l'header X-API-KEY oppure un Bearer
     * token (Authorization: Bearer <chiave>): è valida qualunque chiave
     * presente in tabella.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-API-KEY', '');

        if ($provided === '') {
            $provided = (string) $request->bearerToken();
        }

        if (ApiKey::findBySecret($provided) === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
