@extends('layouts.app')

@push('styles')
@endpush

@section('page_title', 'Usuarios')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Usuarios</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ── Tabs nav (fuera de la card, según patrón UI Kit) ─────────────────── --}}
<ul class="nav nav-tabs" id="usersTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ !in_array(request('tab'), ['suppliers', 'roles']) ? 'active' : '' }}"
                id="tab-users-btn" data-bs-toggle="tab" data-bs-target="#tab-users"
                type="button" role="tab">
            <i class="ri-group-line me-1"></i> Usuarios
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ request('tab') === 'suppliers' ? 'active' : '' }}"
                id="tab-suppliers-btn" data-bs-toggle="tab" data-bs-target="#tab-suppliers"
                type="button" role="tab">
            <i class="ri-truck-line me-1"></i> Proveedores
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ request('tab') === 'roles' ? 'active' : '' }}"
                id="tab-roles-btn" data-bs-toggle="tab" data-bs-target="#tab-roles"
                type="button" role="tab">
            <i class="ri-shield-user-line me-1"></i> Roles
        </button>
    </li>
</ul>

<div class="tab-content mt-0 pt-0" id="usersTabContent">

    {{-- ══════════════════════════════════════════════════════════════
         PESTAÑA 1 – USUARIOS
    ══════════════════════════════════════════════════════════════════ --}}
        <div class="tab-pane fade {{ !in_array(request('tab'), ['suppliers', 'roles']) ? 'show active' : '' }}"
         id="tab-users" role="tabpanel">
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <div>
                            <h4 class="card-title mb-0">Listado de usuarios</h4>
                        </div>
                        <div>
                            @role('admin')
                            <button type="button" class="btn btn-sm btn-primary"
                                    data-bs-toggle="modal" data-bs-target="#modalCreateUser">
                                <i class="ri-user-add-line me-1"></i> Nuevo usuario
                            </button>
                            @endrole
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                                <thead class="bg-light-subtle">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Correo electrónico</th>
                                        <th>Roles</th>
                                        <th>Permisos directos</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($users as $user)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                                        <span class="text-primary fw-semibold">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-dark fw-medium fs-15">{{ $user->name }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ $user->email }}</td>
                                            <td>
                                                @foreach ($user->roles as $role)
                                                    <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">{{ $role->name }}</span>
                                                @endforeach
                                            </td>
                                            <td>
                                                @foreach ($user->permissions as $perm)
                                                    <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">{{ $perm->name }}</span>
                                                @endforeach
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    @role('admin')
                                                    <button class="btn btn-soft-primary btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalEditUser{{ $user->id }}">
                                                        <i class="ri-edit-line align-middle fs-18"></i>
                                                    </button>
                                                    @if ($user->id !== auth()->id())
                                                        <form method="POST"
                                                              action="{{ route('usuarios.destroy', $user) }}"
                                                              class="d-inline"
                                                              onsubmit="return confirm('¿Eliminar a {{ addslashes($user->name) }}?')">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="btn btn-soft-danger btn-sm">
                                                                <i class="ri-delete-bin-line align-middle fs-18"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @endrole
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- Modal edición --}}
                                        <div class="modal fade" id="modalEditUser{{ $user->id }}" tabindex="-1">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Editar usuario — {{ $user->name }}</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="{{ route('usuarios.update', $user) }}">
                                                        @csrf @method('PUT')
                                                        <div class="modal-body row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Nombre</label>
                                                                <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Correo electrónico</label>
                                                                <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Nueva contraseña <small class="text-muted">(dejar en blanco para no cambiar)</small></label>
                                                                <input type="password" name="password" class="form-control" autocomplete="new-password">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Confirmar contraseña</label>
                                                                <input type="password" name="password_confirmation" class="form-control">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Roles</label>
                                                                @foreach ($roles as $role)
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox"
                                                                               name="roles[]" value="{{ $role->name }}"
                                                                               id="edit_role_{{ $user->id }}_{{ $role->id }}"
                                                                               {{ $user->hasRole($role->name) ? 'checked' : '' }}>
                                                                        <label class="form-check-label" for="edit_role_{{ $user->id }}_{{ $role->id }}">
                                                                            {{ $role->name }}
                                                                        </label>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Permisos directos</label>
                                                                @foreach ($permissions as $perm)
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox"
                                                                               name="permissions[]" value="{{ $perm }}"
                                                                               id="edit_perm_{{ $user->id }}_{{ $perm }}"
                                                                               {{ $user->permissions->contains('name', $perm) ? 'checked' : '' }}>
                                                                        <label class="form-check-label" for="edit_perm_{{ $user->id }}_{{ $perm }}">
                                                                            {{ $perm }}
                                                                        </label>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No hay usuarios registrados.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>{{-- /tab-users --}}

    {{-- ══════════════════════════════════════════════════════════════
         PESTAÑA 2 – PROVEEDORES
    ══════════════════════════════════════════════════════════════════ --}}
    <div class="tab-pane fade {{ request('tab') === 'suppliers' ? 'show active' : '' }}"
         id="tab-suppliers" role="tabpanel">
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header border-bottom">
                        <h4 class="card-title mb-0">Cuentas de proveedores</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                                <thead class="bg-light-subtle">
                                    <tr>
                                        <th>Proveedor</th>
                                        <th>Correo electrónico</th>
                                        <th>Estatus de acceso</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($supplierUsers as $supplierUser)
                                        @php
                                            $portalStatus = $supplierUser->supplier
                                                ? ($supplierUser->supplier->portal_access_enabled
                                                    ? ['Activo', 'bg-success-subtle text-success']
                                                    : ['Deshabilitado', 'bg-danger-subtle text-danger'])
                                                : ['Sin proveedor vinculado', 'bg-secondary-subtle text-secondary'];
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                                        <span class="text-primary fw-semibold">{{ strtoupper(substr($supplierUser->name, 0, 1)) }}</span>
                                                    </div>
                                                    <div>
                                                        @if ($supplierUser->supplier)
                                                            <a href="{{ route('suppliers.show', $supplierUser->supplier) }}" class="text-dark fw-medium fs-15">
                                                                {{ $supplierUser->supplier->rfc_name ?? $supplierUser->supplier->commercial_name ?? '—' }}
                                                            </a>
                                                        @else
                                                            <span class="text-dark fw-medium fs-15">{{ $supplierUser->name }}</span>
                                                        @endif
                                                        @if (!$supplierUser->supplier || $supplierUser->name !== ($supplierUser->supplier->rfc_name ?? $supplierUser->supplier->commercial_name))
                                                            <span class="d-block text-muted fs-12">{{ $supplierUser->name }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ $supplierUser->email }}</td>
                                            <td>
                                                <span class="badge {{ $portalStatus[1] }} py-1 px-2 fs-12">{{ $portalStatus[0] }}</span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-soft-primary btn-sm"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalEditSupplierPassword{{ $supplierUser->id }}">
                                                    <i class="ri-key-2-line me-1"></i> Contraseña
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No hay cuentas de proveedores registradas.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>{{-- /tab-suppliers --}}

    {{-- ══════════════════════════════════════════════════════════════
         PESTAÑA 3 – ROLES
    ══════════════════════════════════════════════════════════════════ --}}
    <div class="tab-pane fade {{ request('tab') === 'roles' ? 'show active' : '' }}"
         id="tab-roles" role="tabpanel">
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <div>
                            <h4 class="card-title mb-0">Roles del sistema</h4>
                        </div>
                        <div>
                            @role('admin')
                            <button type="button" class="btn btn-sm btn-primary"
                                    data-bs-toggle="modal" data-bs-target="#modalCreateRole">
                                <i class="ri-shield-star-line me-1"></i> Nuevo rol
                            </button>
                            @endrole
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                                <thead class="bg-light-subtle">
                                    <tr>
                                        <th>Rol</th>
                                        <th>Permisos asignados</th>
                                        <th>Usuarios</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($roles as $role)
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">{{ $role->name }}</span>
                                            </td>
                                            <td>
                                                @foreach ($role->permissions as $perm)
                                                    <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">{{ $perm->name }}</span>
                                                @endforeach
                                            </td>
                                            <td>{{ $role->users()->count() }}</td>
                                            <td>
                                                @if ($role->name !== 'admin')
                                                    @role('admin')
                                                    <div class="d-flex gap-2">
                                                        <form method="POST"
                                                              action="{{ route('roles.destroy', $role) }}"
                                                              class="d-inline"
                                                              onsubmit="return confirm('¿Eliminar el rol {{ addslashes($role->name) }}?')">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="btn btn-soft-danger btn-sm">
                                                                <i class="ri-delete-bin-line align-middle fs-18"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                    @endrole
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Protegido</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No hay roles definidos.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>{{-- /tab-roles --}}

</div>{{-- /tab-content --}}

@foreach ($supplierUsers as $supplierUser)
    <div class="modal fade" id="modalEditSupplierPassword{{ $supplierUser->id }}" tabindex="-1"
         aria-labelledby="modalEditSupplierPasswordLabel{{ $supplierUser->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditSupplierPasswordLabel{{ $supplierUser->id }}">
                        Cambiar contraseña - {{ $supplierUser->name }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('usuarios.supplier_password.update', ['user' => $supplierUser, 'tab' => 'suppliers']) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="supplier_user_id" value="{{ $supplierUser->id }}">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nueva contraseña</label>
                            <input type="password" name="supplier_password"
                                   class="form-control @error('supplier_password') is-invalid @enderror"
                                   autocomplete="new-password" required>
                            @error('supplier_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Confirmar contraseña</label>
                            <input type="password" name="supplier_password_confirmation" class="form-control"
                                   autocomplete="new-password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar contraseña</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

{{-- ══════════════════════════════════════════════════════════════════════════
     MODAL – Crear usuario
══════════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalCreateUser" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('usuarios.store') }}">
                @csrf
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                               autocomplete="new-password" required>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirmar contraseña</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Roles</label>
                        @foreach ($roles as $role)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="roles[]" value="{{ $role->name }}"
                                       id="create_role_{{ $role->id }}"
                                       {{ in_array($role->name, old('roles', [])) ? 'checked' : '' }}>
                                <label class="form-check-label" for="create_role_{{ $role->id }}">{{ $role->name }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Permisos directos</label>
                        @foreach ($permissions as $perm)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="permissions[]" value="{{ $perm }}"
                                       id="create_perm_{{ $perm }}"
                                       {{ in_array($perm, old('permissions', [])) ? 'checked' : '' }}>
                                <label class="form-check-label" for="create_perm_{{ $perm }}">{{ $perm }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     MODAL – Crear rol
══════════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalCreateRole" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo rol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('roles.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del rol</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" placeholder="ej. hr, finance…" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <label class="form-label fw-semibold">Permisos iniciales</label>
                    <div class="d-flex gap-3 flex-wrap">
                        @foreach ($permissions as $perm)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="permissions[]" value="{{ $perm }}"
                                       id="role_perm_{{ $perm }}"
                                       {{ in_array($perm, old('permissions', ['create','read','update','delete'])) ? 'checked' : '' }}>
                                <label class="form-check-label" for="role_perm_{{ $perm }}">{{ $perm }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear rol</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    @if ($errors->has('supplier_password'))
        document.addEventListener('DOMContentLoaded', function () {
            var modal = new bootstrap.Modal(
                document.getElementById('modalEditSupplierPassword{{ old('supplier_user_id') }}')
            );
            modal.show();
        });
    @elseif ($errors->any())
        document.addEventListener('DOMContentLoaded', function () {
            var modal = new bootstrap.Modal(
                document.getElementById('{{ request()->routeIs("roles.*") ? "modalCreateRole" : "modalCreateUser" }}')
            );
            modal.show();
        });
    @endif
</script>
@endpush
