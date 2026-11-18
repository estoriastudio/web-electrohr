<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <title>@yield('code') - @yield('title') | Intranet ElectroHR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <link rel="icon" type="image/png" href="{{ asset('favicon-96x96.png') }}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}" />
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" />

    <link href="{{ asset('assets/css/vendor.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet" type="text/css" />
    <script src="{{ asset('assets/js/config.min.js') }}"></script>
</head>

{{-- Vista autónoma: no usa sesión, auth ni base de datos para poder renderizar aun con la app caída (500/503). --}}
<body class="authentication-bg">
    <div class="account-pages pt-2 pt-sm-5 pb-4 pb-sm-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-5 col-lg-6 col-md-8">
                    <div class="card auth-card">
                        <div class="card-body px-3 py-5 text-center">
                            <div class="mx-auto mb-4 auth-logo">
                                <a href="{{ url('/') }}" class="logo-dark">
                                    <img src="{{ asset('assets/images/logo-dark.png') }}" height="45" alt="ElectroHR">
                                </a>
                                <a href="{{ url('/') }}" class="logo-light">
                                    <img src="{{ asset('assets/images/logo-light.png') }}" height="45" alt="ElectroHR">
                                </a>
                            </div>

                            <div class="avatar-xl mx-auto mb-3">
                                <span class="avatar-title bg-@yield('color', 'primary')-subtle text-@yield('color', 'primary') rounded-circle fs-32">
                                    <i class="ri-@yield('icon', 'error-warning-line')"></i>
                                </span>
                            </div>

                            <h1 class="fw-bold display-4 mb-1 text-@yield('color', 'primary')">@yield('code')</h1>
                            <h2 class="fw-bold text-uppercase fs-18">@yield('title')</h2>
                            <p class="text-muted mt-1 mb-4">@yield('message')</p>

                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                @hasSection('actions')
                                    @yield('actions')
                                @else
                                    <button type="button" class="btn btn-light" onclick="goBack()">
                                        <i class="ri-arrow-left-line align-middle me-1"></i> Regresar
                                    </button>
                                    <a href="{{ url('/') }}" class="btn btn-primary">
                                        <i class="ri-home-4-line align-middle me-1"></i> Ir al inicio
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <p class="text-center text-muted fs-12 mt-3 mb-0">
                        Si el problema continúa, comunícate con el administrador del sistema.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = @json(url('/'));
            }
        }
    </script>
</body>

</html>
