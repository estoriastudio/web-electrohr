<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    // ─── Usuarios ────────────────────────────────────────────────────────────

    public function index()
    {
        $users       = User::with('roles', 'permissions')
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'supplier_portal_access');
            })
            ->orderBy('name')
            ->get();
        $roles       = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->pluck('name');

        return view('users.index', compact('users', 'roles', 'permissions'));
    }

    public function show(User $user)
    {
        // Siempre carga el usuario autenticado, ignorando el parámetro de ruta
        $user = auth()->user()->load('roles', 'permissions');

        return view('users.show', compact('user'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'    => ['required', 'confirmed', Password::min(8)],
            'roles'       => ['nullable', 'array'],
            'roles.*'     => ['string', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($data['roles'] ?? []);
        $user->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', "unique:users,email,{$user->id}"],
            'password'      => ['nullable', 'confirmed', Password::min(8)],
            'roles'         => ['nullable', 'array'],
            'roles.*'       => ['string', 'exists:roles,name'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user->update([
            'name'  => $data['name'],
            'email' => $data['email'],
            ...($data['password'] ? ['password' => Hash::make($data['password'])] : []),
        ]);

        $user->syncRoles($data['roles'] ?? []);
        $user->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'No puedes eliminar tu propia cuenta.');
        $user->delete();

        return redirect()->route('usuarios.index')->with('success', 'Usuario eliminado correctamente.');
    }

    // ─── Roles ───────────────────────────────────────────────────────────────

    public function storeRole(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100', 'unique:roles,name'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('usuarios.index', ['tab' => 'roles'])->with('success', 'Rol creado correctamente.');
    }

    public function destroyRole(Role $role)
    {
        abort_if($role->name === 'admin', 403, 'El rol admin no puede eliminarse.');
        $role->delete();

        return redirect()->route('usuarios.index', ['tab' => 'roles'])->with('success', 'Rol eliminado correctamente.');
    }
}
