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
                <a class="nav-link" href="orders.html">
                    <span class="nav-icon">
                        <i class="ri-dashboard-2-line"></i>
                    </span>
                    <span class="nav-text">Vista General</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="widgets.html">
                    <span class="nav-icon">
                        <i class="ri-shapes-line"></i>
                    </span>
                    <span class="nav-text">Proveedores</span>
                </a>
            </li>

            <li class="menu-title">Compras</li>

            <li class="nav-item">
                <a class="nav-link" href="widgets.html">
                    <span class="nav-icon">
                        <i class="ri-shapes-line"></i>
                    </span>
                    <span class="nav-text">Órdenes de Compra</span>
                </a>
            </li>

            <li class="menu-title">Pagos</li>

            <li class="nav-item">
                <a class="nav-link" href="widgets.html">
                    <span class="nav-icon">
                        <i class="ri-shapes-line"></i>
                    </span>
                    <span class="nav-text">Pagos</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="widgets.html">
                    <span class="nav-icon">
                        <i class="ri-shapes-line"></i>
                    </span>
                    <span class="nav-text">Hitos</span>
                </a>
            </li>

            <li class="menu-title">Configuración</li>

            <li class="nav-item">
                <a class="nav-link menu-arrow" href="#sidebarPages" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarPages">
                    <span class="nav-icon">
                        <i class="ri-pages-line"></i>
                    </span>
                    <span class="nav-text"> Usuarios </span>
                </a>
                <div class="collapse" id="sidebarPages">
                    <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="pages-starter.html">Listado</a>
                        </li>
                        
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="pages-calendar.html">Roles y Permisos</a>
                        </li>
                    </ul>
                </div>
            </li> <!-- end Users Menu -->

            <li class="nav-item">
                <a class="nav-link" href="widgets.html">
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