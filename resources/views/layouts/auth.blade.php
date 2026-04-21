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
     <link rel="shortcut icon" href="assets/images/favicon.ico">

     <!-- Vendor css (Require in all Page) -->
     <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />

     <!-- Icons css (Require in all Page) -->
     <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />

     <!-- App css (Require in all Page) -->
     <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />

     <!-- Theme Config js (Require in all Page) -->
     <script src="assets/js/config.min.js"></script>

     @stack('styles')
</head>

<body class="authentication-bg">

     <div class="account-pages pt-2 pt-sm-5 pb-4 pb-sm-5">
        @yield('content')
     </div>

     <!-- Vendor Javascript (Require in all Page) -->
     <script src="assets/js/vendor.js"></script>

     <!-- App Javascript (Require in all Page) -->
     <script src="assets/js/app.js"></script>
     @stack('scripts')
</body>
</html>