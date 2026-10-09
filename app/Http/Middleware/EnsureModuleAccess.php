<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;

/**
 * Acceso por modulo: lectura (GET/HEAD) exige `<modulo>.read`; cualquier otro metodo
 * exige al menos un permiso de escritura (`create`, `update` o `delete`) del modulo.
 * Uso: `module:invoices,payments` permite el acceso con cualquiera de los modulos.
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string ...$modules)
    {
        $user = $request->user();

        if (! $user) {
            throw UnauthorizedException::notLoggedIn();
        }

        $actions = $request->isMethodSafe() ? ['read'] : ['create', 'update', 'delete'];
        $abilities = [];
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $abilities[] = "{$module}.{$action}";
            }
        }

        if (! $user->canAny($abilities)) {
            throw UnauthorizedException::forPermissions($abilities);
        }

        return $next($request);
    }
}
