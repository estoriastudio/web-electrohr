<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupplierPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasRole('supplier_portal_access')) {
            abort(403, 'Acceso no autorizado al portal de proveedores.');
        }

        $user->loadMissing('supplier');

        if (!$user->supplier || !$user->supplier->portal_access_enabled) {
            abort(403, 'El acceso al portal se encuentra deshabilitado para este proveedor.');
        }

        return $next($request);
    }
}
