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

            @hasanyrole('admin|Orden de compra')
            @can('read')
            <li class="nav-item">
                <a class="nav-link" href="{{ route('suppliers.index') }}">
                    <span class="nav-icon">
                        <i class="ri-shapes-line"></i>
                    </span>
                    <span class="nav-text">Proveedores</span>
                </a>
            </li>
            @endcan
            @endhasanyrole

            @hasanyrole('admin|Moviles')
            @can('read')
            <li class="nav-item">
                <a class="nav-link" href="{{ route('mobile_assets.index') }}">
                    <span class="nav-icon">
                        <i class="ri-shapes-line"></i>
                    </span>
                    <span class="nav-text">Bienes Móviles</span>
                </a>
            </li>
            @endcan
            @endhasanyrole

            @hasanyrole('admin|Proyectos')
            @can('read')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}" href="{{ route('projects.index') }}">
                    <span class="nav-icon">
                        <i class="ri-building-2-line"></i>
                    </span>
                    <span class="nav-text">Proyectos</span>
                    @if($activeProjectsCount > 0)
                        <span class="badge bg-success badge-pill">{{ $activeProjectsCount }}</span>
                    @endif
                </a>
            </li>
            @endcan
            @endhasanyrole

            @hasanyrole('admin|Solmat')
            @can('read')
            <li class="menu-title">Almacén</li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('material_requests.*') ? 'active' : '' }}"
                   href="{{ route('material_requests.index') }}">
                    <span class="nav-icon">
                        <i class="ri-file-add-line"></i>
                    </span>
                    <span class="nav-text">Solicitudes de Material</span>
                </a>
            </li>
            @endcan
            @endhasanyrole

            @hasanyrole('admin|Solmat')
            @can('read')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('warehouse.solmat_pile') ? 'active' : '' }}"
                   href="{{ route('warehouse.solmat_pile') }}">
                    <span class="nav-icon">
                        <i class="ri-inbox-2-line"></i>
                    </span>
                    <span class="nav-text">Pila SOLMAT</span>
                </a>
            </li>
            @endcan
            @endhasanyrole

            @hasanyrole('admin|Solcom|Orden de compra')
            @can('read')
            <li class="menu-title">Compras</li>
            @endcan
            @endhasanyrole

            @hasanyrole('admin|Solcom')
            @can('read')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('purchase_requests.*') ? 'active' : '' }}"
                   href="{{ route('purchase_requests.index') }}">
                    <span class="nav-icon">
                        <i class="ri-shopping-cart-2-line"></i>
                    </span>
                    <span class="nav-text">Solicitudes de Compra</span>
                </a>
            </li>
            @endcan
            @endhasanyrole

            @hasanyrole('admin|Solcom')
            @can('read')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('purchasing.solcom_pile') ? 'active' : '' }}"
                   href="{{ route('purchasing.solcom_pile') }}">
                    <span class="nav-icon">
                        <i class="ri-stack-line"></i>
                    </span>
                    <span class="nav-text">Pila SOLCOM</span>
                </a>
            </li>
            @endcan
            @endhasanyrole

            @hasanyrole('admin|Solcom')
            @can('read')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('purchasing.workload') ? 'active' : '' }}"
                   href="{{ route('purchasing.workload') }}">
                    <span class="nav-icon">
                        <i class="ri-bar-chart-grouped-line"></i>
                    </span>
                    <span class="nav-text">Carga de Trabajo</span>
                </a>
            </li>
            @endcan
            @endhasanyrole

            @hasanyrole('admin|Pagos|Orden de compra')
            @can('read')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('purchase_orders.*') ? 'active' : '' }}"
                   href="{{ route('purchase_orders.index') }}">
                    <span class="nav-icon">
                        <i class="ri-file-list-3-line"></i>
                    </span>
                    <span class="nav-text">Órdenes de Compra</span>
                </a>
            </li>
            @endcan
            @endhasanyrole

            @hasanyrole('admin|Pagos')
            @can('read')
            <li class="menu-title">Pagos</li>

            @hasanyrole('admin|Pagos|Orden de compra')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('milestones.*') ? 'active' : '' }}"
                   href="{{ route('milestones.index') }}">
                    <span class="nav-icon">
                        <i class="ri-file-list-3-line"></i>
                    </span>
                    <span class="nav-text">Hitos de Pago</span>
                </a>
            </li>
            @endhasanyrole

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('payments.index') ? 'active' : '' }}"
                   href="{{ route('payments.index') }}">
                    <span class="nav-icon">
                        <i class="ri-shield-check-line"></i>
                    </span>
                    <span class="nav-text">Autorización de Pagos</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('payments.alta_facturas') ? 'active' : '' }}"
                   href="{{ route('payments.alta_facturas') }}">
                    <span class="nav-icon">
                        <i class="ri-upload-2-line"></i>
                    </span>
                    <span class="nav-text">Alta de Facturas</span>
                </a>
            </li>
            @endcan
            @endhasanyrole

            @role('admin')
            <li class="menu-title">Configuración</li>

            <li class="nav-item">
                <a class="nav-link menu-arrow {{ request()->routeIs('concepts.*') || request()->routeIs('concept_categories.*') ? 'active' : '' }}" href="#sidebarConcepts" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarConcepts">
                    <span class="nav-icon">
                        <i class="ri-pages-line"></i>
                    </span>
                    <span class="nav-text"> Suministros </span>
                </a>
                <div class="collapse" id="sidebarConcepts">
                    <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item">
                            <a class="sub-nav-link {{ request()->routeIs('concepts.*') ? 'active' : '' }}" href="{{ route('concepts.index') }}">Conceptos</a>
                        </li>
                        <li class="sub-nav-item">
                            <a class="sub-nav-link {{ request()->routeIs('concept_categories.*') ? 'active' : '' }}" href="{{ route('concept_categories.index') }}">Familias</a>
                        </li>
                    </ul>
                </div>
            </li>
            

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
            @endrole
        </ul>
    </div>
</div>
<!-- ========== App Menu End ========== -->