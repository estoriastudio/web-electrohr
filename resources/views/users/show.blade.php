@extends('layouts.app')

@section('page_title', 'Mi perfil')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Mi perfil</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@php
    $gravatarHash = md5(strtolower(trim($user->email)));
    $gravatarUrl  = "https://www.gravatar.com/avatar/{$gravatarHash}?s=128&d=identicon";
@endphp

<div class="row g-3">

    {{-- ── Columna izquierda: avatar + info ──────────────────────────────── --}}
    <div class="col-xl-4 col-lg-5">
        <div class="card text-center">
            <div class="card-body py-4">
                <img src="{{ $gravatarUrl }}" alt="{{ $user->name }}"
                     class="rounded-circle mb-3" width="80" height="80"
                     style="image-rendering: pixelated;">
                <h5 class="fw-semibold mb-1">{{ $user->name }}</h5>
                <p class="text-muted fs-13 mb-3">{{ $user->email }}</p>

                @if ($user->roles->isNotEmpty())
                    <div class="d-flex flex-wrap justify-content-center gap-1 mb-1">
                        @foreach ($user->roles as $role)
                            <span class="badge bg-primary-subtle text-primary px-2 py-1 fs-12">{{ $role->name }}</span>
                        @endforeach
                    </div>
                @endif

                <p class="text-muted fs-12 mt-3 mb-0">
                    <i class="ri-calendar-line me-1"></i>Miembro desde {{ $user->created_at->format('d/m/Y') }}
                </p>
            </div>
        </div>
    </div>

    {{-- ── Columna derecha: formularios ──────────────────────────────────── --}}
    <div class="col-xl-8 col-lg-7">

        {{-- Datos generales --}}
        <div class="card mb-3">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-user-line me-1 text-muted"></i> Información general</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('usuarios.update', $user) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $user->name) }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $user->email) }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Campos ocultos para no perder roles/permisos al actualizar --}}
                    @foreach ($user->roles->pluck('name') as $role)
                        <input type="hidden" name="roles[]" value="{{ $role }}">
                    @endforeach
                    @foreach ($user->permissions->pluck('name') as $perm)
                        <input type="hidden" name="permissions[]" value="{{ $perm }}">
                    @endforeach

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ri-save-line me-1"></i> Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Cambiar contraseña --}}
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-lock-line me-1 text-muted"></i> Cambiar contraseña</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('usuarios.update', $user) }}">
                    @csrf
                    @method('PUT')

                    {{-- Mantener nombre y email para pasar validación --}}
                    <input type="hidden" name="name"  value="{{ $user->name }}">
                    <input type="hidden" name="email" value="{{ $user->email }}">
                    @foreach ($user->roles->pluck('name') as $role)
                        <input type="hidden" name="roles[]" value="{{ $role }}">
                    @endforeach
                    @foreach ($user->permissions->pluck('name') as $perm)
                        <input type="hidden" name="permissions[]" value="{{ $perm }}">
                    @endforeach

                    <div class="mb-3">
                        <label class="form-label">Nueva contraseña</label>
                        <input type="password" name="password"
                               class="form-control @error('password') is-invalid @enderror"
                               autocomplete="new-password">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Mínimo 8 caracteres, mayúsculas, minúsculas y números.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirmar contraseña</label>
                        <input type="password" name="password_confirmation"
                               class="form-control" autocomplete="new-password">
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="ri-key-line me-1"></i> Actualizar contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

@endsection
