<?php

namespace App\Http\Controllers;

use App\Services\AccessSimulation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Selector de la barra superior: ver el ERP como un rol o entrar como un usuario (solo el admin configurado). */
class AccessSimulationController extends Controller
{
    public function __construct(private readonly AccessSimulation $simulation) {}

    public function start(Request $request): RedirectResponse
    {
        $this->ensureAllowed();

        $data = $request->validate([
            'role' => ['required', 'string', Rule::in($this->simulation->availableRoles()->pluck('name')->all())],
            'user_id' => ['nullable', 'integer', Rule::in($this->simulation->availableUsers()->pluck('id')->all())],
        ], [
            'user_id.in' => 'El usuario elegido no tiene un rol de la matriz.',
        ]);

        if (empty($data['user_id']) && $this->simulation->roleRequiresUser($data['role'])) {
            throw ValidationException::withMessages(['user_id' => 'Para ver el portal de proveedores elige un usuario proveedor.']);
        }

        $session = $request->session();
        $session->forget([AccessSimulation::SESSION_ROLE, AccessSimulation::SESSION_USER]);

        if (! empty($data['user_id'])) {
            $session->put(AccessSimulation::SESSION_USER, (int) $data['user_id']);
            $message = 'Ahora navegas como el usuario elegido.';
        } else {
            $session->put(AccessSimulation::SESSION_ROLE, $data['role']);
            $message = "Ahora ves el sistema como {$data['role']}.";
        }

        return redirect()->route('dashboard')->with('success', $message);
    }

    public function stop(Request $request): RedirectResponse
    {
        $this->ensureAllowed();

        $request->session()->forget([AccessSimulation::SESSION_ROLE, AccessSimulation::SESSION_USER]);

        return redirect()->route('dashboard')->with('success', 'Volviste a tu usuario admin.');
    }

    private function ensureAllowed(): void
    {
        // El middleware deja aquí al admin real; sin él la función está apagada o no es su usuario.
        abort_unless($this->simulation->realUser() !== null, $this->simulation->enabled() ? 403 : 404);
    }
}
