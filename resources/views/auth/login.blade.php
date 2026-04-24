@extends('layouts.auth')

@push('styles')
@endpush

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-5">
                    <div class="card auth-card">
                        <div class="card-body px-3 py-5">
                            <div class="mx-auto mb-4 text-center auth-logo">
                                <a href="{{ url('/') }}" class="logo-dark">
                                    <img src="{{ asset('assets/images/logo-dark.png') }}" height="45" alt="logo dark">
                                </a>

                                <a href="{{ url('/') }}" class="logo-light">
                                    <img src="{{ asset('assets/images/logo-light.png') }}" height="45" alt="logo light">
                                </a>
                            </div>

                            <h2 class="fw-bold text-uppercase text-center fs-18">Inicio de sesión</h2>
                            <p class="text-muted text-center mt-1 mb-4">Ingrese su dirección de correo electrónico y contraseña para acceder al panel de administración.</p>

                            <div class="px-4">
                                @if (session('status'))
                                    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
                                @endif

                                @if ($errors->any())
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('login') }}" class="authentication-form">
                                    @csrf

                                    <div class="mb-3">
                                        <label class="form-label" for="email">Correo electrónico</label>
                                        <input type="email" id="email" name="email" class="form-control bg-light bg-opacity-50 border-light py-2 @error('email') is-invalid @enderror" placeholder="Ingrese su correo electrónico" value="{{ old('email') }}" required autofocus>
                                        @error('email')
                                            <span class="text-danger small">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        @if (Route::has('password.request'))
                                            <a href="{{ route('password.request') }}" class="float-end text-muted text-unline-dashed ms-1">Restablecer contraseña</a>
                                        @endif
                                        <label class="form-label" for="password">Contraseña</label>
                                        <input type="password" id="password" name="password" class="form-control bg-light bg-opacity-50 border-light py-2 @error('password') is-invalid @enderror" placeholder="Ingrese su contraseña" required autocomplete="current-password">
                                        @error('password')
                                            <span class="text-danger small">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="remember">Recordarme</label>
                                        </div>
                                    </div>

                                    <div class="mb-1 text-center d-grid">
                                        <button class="btn btn-danger py-2 fw-medium" type="submit">Iniciar sesión</button>
                                    </div>
                                </form>
                            </div> <!-- end col -->
                        </div> <!-- end card-body -->
                    </div> <!-- end card -->

                    <p class="mb-0 text-center text-white">¿Problemas? <a href="#" class="text-reset text-unline-dashed fw-bold ms-1">Contacta a soporte</a></p>

            </div> <!-- end col -->
        </div> <!-- end row -->
    </div>
@endsection

@push('scripts')
@endpush