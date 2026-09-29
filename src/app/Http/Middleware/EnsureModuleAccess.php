<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    /**
     * Uso en rutas:
     *   ->middleware('module:no-conformidad')          exige Lector
     *   ->middleware('module:no-conformidad,create')   exige Creador
     *   ->middleware('module:no-conformidad,edit')     exige Editor
     */
    public function handle(Request $request, Closure $next, string $moduleKey, string $ability = 'read'): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasModuleAccess($moduleKey, $ability)) {
            abort(403, 'No tienes acceso a este módulo.');
        }

        return $next($request);
    }
}