<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AccessSimulation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica la simulación de accesos guardada en sesión antes de que corran los middleware
 * `auth` y `permission:` de cada ruta, para que menú, rutas y vistas respondan como el rol
 * o el usuario elegidos.
 */
class ApplyAccessSimulation
{
    public function __construct(private readonly AccessSimulation $simulation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->simulation->reset();
        $user = $request->user();

        if (! $user instanceof User || ! $this->simulation->canUse($user)) {
            return $next($request);
        }

        $this->simulation->setRealUser($user);

        // Cerrar sesión y salir de la simulación siempre se atienden como el admin real.
        if ($request->routeIs('logout', 'access_simulation.*')) {
            return $next($request);
        }

        $session = $request->session();

        if ($userId = $session->get(AccessSimulation::SESSION_USER)) {
            $target = User::query()->with('roles')->find($userId);

            if ($target) {
                $this->simulation->applyUser($target);
                Auth::guard()->setUser($target);
            } else {
                $session->forget(AccessSimulation::SESSION_USER);
            }
        } elseif ($roleName = $session->get(AccessSimulation::SESSION_ROLE)) {
            if (! $this->simulation->applyRole($user, $roleName)) {
                $session->forget(AccessSimulation::SESSION_ROLE);
            }
        }

        return $next($request);
    }
}
