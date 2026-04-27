<!-- ========== App Menu Start ========== -->
<div class="main-nav">
    <!-- Sidebar Logo -->
    <div class="logo-box">
        <a href="index.html" class="logo-dark">
            <img src="{{ asset('assets/images/logo-sm.png') }}" class="logo-sm" alt="logo sm">
            <img src="{{ asset('assets/images/logo-dark.png') }}" class="logo-lg" alt="logo dark">
        </a>

        <a href="index.html" class="logo-light">
            <img src="{{ asset('assets/images/logo-sm.png') }}" class="logo-sm" alt="logo sm">
            <img src="{{ asset('assets/images/logo-light.png') }}" class="logo-lg" alt="logo light">
        </a>
    </div>

    <!-- Menu Toggle Button (sm-hover) -->
    <button type="button" class="button-sm-hover" aria-label="Show Full Sidebar">
        <i class="ri-menu-2-line fs-24 button-sm-hover-icon"></i>
    </button>

    <div class="scrollbar" data-simplebar>
        <ul class="navbar-nav" id="navbar-nav">
            <li class="menu-title">Menu</li>

            <li class="nav-item">
                <a class="nav-link" href="{{ route('dashboard') }}">
                    <span class="nav-icon">
                        <i class="ri-dashboard-2-line"></i>
                    </span>
                    <span class="nav-text">Vista General</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="{{ route('suppliers.index') }}">
                    <span class="nav-icon">
                        <i class="ri-shapes-line"></i>
                    </span>
                    <span class="nav-text">Proveedores</span>
                </a>
            </li>

            <li class="menu-title">Compras</li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('purchase_orders.*') ? 'active' : '' }}"
                   href="{{ route('purchase_orders.index') }}">
                    <span class="nav-icon">
                        <i class="ri-file-list-3-line"></i>
                    </span>
                    <span class="nav-text">Órdenes de Compra</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('milestones.*') ? 'active' : '' }}"
                   href="{{ route('milestones.index') }}">
                    <span class="nav-icon">
                        <i class="ri-file-list-3-line"></i>
                    </span>
                    <span class="nav-text">Hitos de Pago</span>
                </a>
            </li>

            <li class="menu-title">Pagos</li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('payments.index') ? 'active' : '' }}"
                   href="{{ route('payments.index') }}">
                    <span class="nav-icon">
                        <i class="ri-shield-check-line"></i>
                    </span>
                    <span class="nav-text">Autorización de Pagos</span>
                </a>
            </li>

            <li class="menu-title">Configuración</li>

            <li class="nav-item">
                <a class="nav-link" href="{{ route('usuarios.index') }}">
                    <span class="nav-icon">
                        <i class="ri-pages-line"></i>
                    </span>
                    <span class="nav-text">Usuarios</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="{{ route('notifications.index') }}">
                    <span class="nav-icon">
                        <i class="ri-shapes-line"></i>
                    </span>
                    <span class="nav-text">Notificaciones</span>
                    <span class="badge bg-danger badge-pill text-end">Hot</span>
                </a>
            </li>
        </ul>
    </div>
</div>
<!-- ========== App Menu End ========== -->