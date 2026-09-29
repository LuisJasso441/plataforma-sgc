<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Debe estar autenticado y tener rol admin (Sistemas)
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Acceso restringido al administrador de plataforma.');
        }

        return $next($request);
    }
}