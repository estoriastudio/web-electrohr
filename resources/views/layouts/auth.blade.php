<!DOCTYPE html>
<html lang="es">

<head>
    <!-- Title Meta -->
    <meta charset="utf-8" />
    <title>Inicio Sesión | Intranet ElectroHR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ERP Interno de ElectroHR" />
    <meta name="author" content="Estoria Studio" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- App favicon -->
    <link rel="icon" type="image/png" href="{{ asset('favicon-96x96.png') }}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}" />
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}" />
    <meta name="apple-mobile-web-app-title" content="ElectroHR" />
    <link rel="manifest" href="{{ asset('site.webmanifest') }}" />

    <!-- Vendor css (Require in all Page) -->
    <link href="{{ asset('assets/css/vendor.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- Icons css (Require in all Page) -->
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- App css (Require in all Page) -->
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- Theme Config js (Require in all Page) -->
    <script src="{{ asset('assets/js/config.min.js') }}"></script>

    @stack('styles')

    <style>
        .authentication-bg-video{
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
        }

        .video-bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg,hsl(205deg,46%,30%) 0,hsl(260deg,29%,36%) 100%);
            z-index: 0;
            opacity: .7;
        }
    </style>
</head>

<body class="authentication-bg">
    <!-- Video BG -->
    <div class="video-bg-overlay"></div>
    <video autoplay muted loop id="auth-video" class="authentication-bg-video">
        <source src="{{ asset('video-bg.mp4') }}" type="video/mp4">
        Your browser does not support the video tag.
    </video>
    
     <div class="account-pages pt-2 pt-sm-5 pb-4 pb-sm-5">
        @yield('content')
     </div>

     <!-- Vendor Javascript (Require in all Page) -->
     <script src="{{ asset('assets/js/vendor.js') }}"></script>

     <!-- App Javascript (Require in all Page) -->
     <script src="{{ asset('assets/js/app.js') }}"></script>
     @stack('scripts')
</body>
</html>