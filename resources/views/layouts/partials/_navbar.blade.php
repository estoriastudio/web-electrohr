<!-- ========== App Menu Start ========== -->
<div class="main-nav">
    <!-- Sidebar Logo -->
    <div class="logo-box">
        <a href="{{ route('dashboard') }}" class="logo-dark">
            <img src="{{ asset('assets/images/logo-sm.png') }}" class="logo-sm" alt="logo sm">
            <img src="{{ asset('assets/images/logo-dark.png') }}" class="logo-lg" alt="logo dark">
        </a>

        <a href="{{ route('dashboard') }}" class="logo-light">
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
                <a class="nav-link" href="{{ auth()->user()->hasRole('supplier_portal_access') ? route('supplier_portal.dashboard') : route('dashboard') }}">
                    <span class="nav-icon">
                        <i class="ri-dashboard-2-line"></i>
                    </span>
                    <span class="nav-text">Vista General</span>
                </a>
            </li>

            @hasanyrole('supplier_portal_access')
            <li class="menu-title">Portal Proveedor</li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('supplier_portal.purchase_orders.*') ? 'active' : '' }}"
                   href="{{ route('supplier_portal.purchase_orders.index') }}">
                    <span class="nav-icon">
                        <i class="ri-file-list-3-line"></i>
                    </span>
                    <span class="nav-text">Mis Órdenes de Compra</span>
                </a>
            </li>

            @if ($hasMaterialVouchers)
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('supplier_portal.material_vouchers.*') ? 'active' : '' }}"
                       href="{{ route('supplier_portal.material_vouchers.index') }}">
                        <span class="nav-icon">
                            <i class="ri-receipt-line"></i>
                        </span>
                        <span class="nav-text">Mis Vales de Material</span>
                    </a>
                </li>
            @endif

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('supplier_portal.account_statement.*') ? 'active' : '' }}"
                   href="{{ route('supplier_portal.account_statement.index') }}">
                    <span class="nav-icon">
                        <i class="ri-bank-card-line"></i>
                    </span>
                    <span class="nav-text">Estado de Cuenta</span>
                </a>
            </li>
            @endhasanyrole

            @canany(['suppliers.view', 'supplier_account_statements.view'])
                    <li class="nav-item">
                        <a class="nav-link menu-arrow {{ request()->routeIs('suppliers.*') ? 'active' : '' }}"
                           href="#sidebarSuppliers" data-bs-toggle="collapse" role="button"
                           aria-expanded="{{ request()->routeIs('suppliers.*') ? 'true' : 'false' }}" aria-controls="sidebarSuppliers">
                            <span class="nav-icon">
                                <i class="ri-shapes-line"></i>
                            </span>
                            <span class="nav-text">Proveedores</span>
                        </a>
                        <div class="collapse {{ request()->routeIs('suppliers.*') ? 'show' : '' }}" id="sidebarSuppliers">
                            <ul class="nav sub-navbar-nav">
                                @can('suppliers.view')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('suppliers.*') && !request()->routeIs('suppliers.account_statement.*') ? 'active' : '' }}"
                                       href="{{ route('suppliers.index') }}">Listado</a>
                                </li>
                                @endcan
                                @can('supplier_account_statements.view')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('suppliers.account_statement.*') ? 'active' : '' }}"
                                       href="{{ route('suppliers.account_statement.index') }}">Estado de Cuenta</a>
                                </li>
                                @endcan
                            </ul>
                        </div>
                    </li>
            @endcanany

            @can('mobile_assets.view')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('mobile_assets.index') }}">
                            <span class="nav-icon">
                                <i class="ri-shapes-line"></i>
                            </span>
                            <span class="nav-text">Bienes Móviles</span>
                        </a>
                    </li>
            @endcan

            @canany(['tools.view', 'tool_controls.view', 'tool_categories.view'])
                    <li class="nav-item">
                        <a class="nav-link menu-arrow {{ request()->routeIs('tools.*') || request()->routeIs('tool_categories.*') ? 'active' : '' }}" href="#sidebarTools" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarTools">
                            <span class="nav-icon">
                                <i class="ri-hammer-line"></i>
                            </span>
                            <span class="nav-text"> Herramientas </span>
                        </a>
                        <div class="collapse" id="sidebarTools">
                            <ul class="nav sub-navbar-nav">
                                @can('tools.view')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('tools.*') ? 'active' : '' }}" href="{{ route('tools.index') }}">Registro</a>
                                </li>
                                @endcan
                                @can('tool_controls.view')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('tool_controls.*') ? 'active' : '' }}" href="{{ route('tool_controls.index') }}">Control de uso</a>
                                </li>
                                @endcan
                                @can('tool_categories.view')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('tool_categories.*') ? 'active' : '' }}" href="{{ route('tool_categories.index') }}">Categorias</a>
                                </li>
                                @endcan
                            </ul>
                        </div>
                    </li>
            @endcanany

            @if($canSeeAllProjects)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('projects.*', 'project_works.*') ? 'active' : '' }}" href="{{ route('projects.index') }}">
                    <span class="nav-icon">
                        <i class="ri-building-2-line"></i>
                    </span>
                    <span class="nav-text">Proyectos</span>
                    @if($activeProjectsCount > 0)
                        <span class="badge bg-success badge-pill">{{ $activeProjectsCount }}</span>
                    @endif
                </a>
            </li>
            @endif

            @if($showMyProjects)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('my-projects.*') ? 'active' : '' }}" href="{{ route('my-projects.index') }}">
                    <span class="nav-icon">
                        <i class="ri-building-2-line"></i>
                    </span>
                    <span class="nav-text">Mis Proyectos</span>
                    @if($myActiveProjectsCount > 0)
                        <span class="badge bg-success badge-pill">{{ $myActiveProjectsCount }}</span>
                    @endif
                </a>
            </li>
            @endif

            @canany(['hr_workers.view', 'hr_worker_groups.view', 'hr_attendances.view', 'hr_vacations.view', 'hr_terminations.view', 'hr_position_categories.view', 'hr_payroll.view', 'hr_piecework.view', 'hr_incentives.view'])
            <li class="menu-title">Recursos Humanos</li>

            @can('hr_workers.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('human_resources.workers.*') ? 'active' : '' }}" href="{{ route('human_resources.workers.index') }}">
                    <span class="nav-icon">
                        <i class="ri-user-3-line"></i>
                    </span>
                    <span class="nav-text">Trabajadores</span>
                </a>
            </li>
            @endcan

            @can('hr_worker_groups.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('human_resources.worker-groups.*') ? 'active' : '' }}" href="{{ route('human_resources.worker-groups.index') }}">
                    <span class="nav-icon">
                        <i class="ri-team-line"></i>
                    </span>
                    <span class="nav-text">Cuadrillas</span>
                </a>
            </li>
            @endcan

            @can('hr_attendances.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('human_resources.worker-attendances.*') ? 'active' : '' }}" href="{{ route('human_resources.worker-attendances.index') }}">
                    <span class="nav-icon">
                        <i class="ri-calendar-check-line"></i>
                    </span>
                    <span class="nav-text">Asistencias</span>
                </a>
            </li>
            @endcan

            @can('hr_vacations.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('human_resources.worker-vacations.*') ? 'active' : '' }}" href="{{ route('human_resources.worker-vacations.index') }}">
                    <span class="nav-icon">
                        <i class="ri-calendar-event-line"></i>
                    </span>
                    <span class="nav-text">Vacaciones</span>
                </a>
            </li>
            @endcan

            @can('hr_terminations.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('human_resources.worker-terminations.*') ? 'active' : '' }}" href="{{ route('human_resources.worker-terminations.index') }}">
                    <span class="nav-icon">
                        <i class="ri-user-unfollow-line"></i>
                    </span>
                    <span class="nav-text">Bajas</span>
                </a>
            </li>
            @endcan

            @can('hr_position_categories.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('human_resources.position-categories.*') ? 'active' : '' }}" href="{{ route('human_resources.position-categories.index') }}">
                    <span class="nav-icon">
                        <i class="ri-briefcase-4-line"></i>
                    </span>
                    <span class="nav-text">Categorías de puesto</span>
                </a>
            </li>
            @endcan

            @can('hr_payroll.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('human_resources.payroll-periods.*') || request()->routeIs('human_resources.payroll-lines.*') || request()->routeIs('human_resources.payroll.*') ? 'active' : '' }}" href="{{ route('human_resources.payroll-periods.index') }}">
                    <span class="nav-icon">
                        <i class="ri-money-dollar-circle-line"></i>
                    </span>
                    <span class="nav-text">Nómina</span>
                </a>
            </li>
            @endcan

            @can('hr_piecework.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('human_resources.piecework-entries.*') ? 'active' : '' }}" href="{{ route('human_resources.piecework-entries.index') }}">
                    <span class="nav-icon">
                        <i class="ri-hammer-line"></i>
                    </span>
                    <span class="nav-text">Destajo</span>
                </a>
            </li>
            @endcan

            @can('hr_incentives.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('human_resources.incentives.*') ? 'active' : '' }}" href="{{ route('human_resources.incentives.index') }}">
                    <span class="nav-icon">
                        <i class="ri-medal-line"></i>
                    </span>
                    <span class="nav-text">Incentivos</span>
                </a>
            </li>
            @endcan
            @endcanany

            @canany(['material_requests.view', 'material_vouchers.view', 'material_request_changes.view', 'material_request_queue.view'])
            <li class="menu-title">Almacén</li>

            {{-- Gerente y Supervisor ven sus SOLMAT en Mis Proyectos. --}}
            @if(auth()->user()->can('material_requests.view') && auth()->user()->hasFullAccessScope('material_requests'))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('material_requests.*') ? 'active' : '' }}"
                   href="{{ route('material_requests.index') }}">
                    <span class="nav-icon">
                        <i class="ri-file-add-line"></i>
                    </span>
                    <span class="nav-text">SOLMATs</span>
                </a>
            </li>
            @endif

            @can('material_vouchers.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('material_vouchers.*') ? 'active' : '' }}"
                   href="{{ route('material_vouchers.index') }}">
                    <span class="nav-icon">
                        <i class="ri-receipt-line"></i>
                    </span>
                    <span class="nav-text">Vales de Material</span>
                </a>
            </li>
            @endcan

            @can('material_request_changes.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('material_request_changes.*') ? 'active' : '' }}"
                   href="{{ route('material_request_changes.index') }}">
                    <span class="nav-icon">
                        <i class="ri-edit-circle-line"></i>
                    </span>
                    <span class="nav-text">Cambios SOLMAT</span>
                    @if(($materialRequestChangesPendingCount ?? 0) > 0)
                        <span class="badge bg-warning badge-pill">{{ $materialRequestChangesPendingCount }}</span>
                    @endif
                </a>
            </li>
            @endcan

            @can('material_request_queue.view')
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
            @endcanany

            @can('purchase_order_receipts.register')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('purchase_orders.delivery_report') ? 'active' : '' }}"
                   href="{{ route('purchase_orders.delivery_report') }}">
                    <span class="nav-icon">
                        <i class="ri-file-list-3-line"></i>
                    </span>
                    <span class="nav-text">Buscar OCs</span>
                </a>
            </li>
            @endcan

            @canany(['stocks.view', 'stock_entries.view', 'stock_exits.view'])
            <li class="menu-title">Inventario</li>
            @can('stocks.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('stocks.index') ? 'active' : '' }}" href="{{ route('stocks.index') }}">
                    <span class="nav-icon"><i class="ri-archive-line"></i></span>
                    <span class="nav-text">Consulta</span>
                </a>
            </li>
            @endcan
            @can('stock_entries.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('stocks.entries.*') ? 'active' : '' }}" href="{{ route('stocks.entries.index') }}">
                    <span class="nav-icon"><i class="ri-inbox-line"></i></span>
                    <span class="nav-text">Entradas</span>
                </a>
            </li>
            @endcan
            @can('stock_exits.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('stocks.exits.*') ? 'active' : '' }}" href="{{ route('stocks.exits.index') }}">
                    <span class="nav-icon"><i class="ri-arrow-right-circle-line"></i></span>
                    <span class="nav-text">Salidas</span>
                </a>
            </li>
            @endcan
            @endcanany

            @canany(['purchase_requests.view', 'purchase_request_changes.view', 'purchase_request_queue.view', 'purchasing_workload.view', 'purchase_orders.view'])
            <li class="menu-title">Compras</li>
            @endcanany

            {{-- Gerente y Supervisor consultan SOLCOM y OC de sus proyectos en Mis Proyectos. --}}
            @if(auth()->user()->can('purchase_requests.view') && auth()->user()->hasFullAccessScope('purchase_requests'))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('purchase_requests.*') ? 'active' : '' }}"
                   href="{{ route('purchase_requests.index') }}">
                    <span class="nav-icon">
                        <i class="ri-shopping-cart-2-line"></i>
                    </span>
                    <span class="nav-text">SOLCOMs</span>
                </a>
            </li>
            @endif

            @can('purchase_request_changes.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('purchase_request_changes.*') ? 'active' : '' }}"
                   href="{{ route('purchase_request_changes.index') }}">
                    <span class="nav-icon">
                        <i class="ri-edit-circle-line"></i>
                    </span>
                    <span class="nav-text">Cambios SOLCOM</span>
                    @if(($purchaseRequestChangesPendingCount ?? 0) > 0)
                        <span class="badge bg-warning badge-pill">{{ $purchaseRequestChangesPendingCount }}</span>
                    @endif
                </a>
            </li>
            @endcan

            @can('purchase_request_queue.view')
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

            @can('purchasing_workload.view')
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

            @if(auth()->user()->hasFullAccessScope('purchase_orders') || auth()->user()->can('purchase_orders.create'))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('purchase_orders.*') ? 'active' : '' }}"
                   href="{{ route('purchase_orders.index') }}">
                    <span class="nav-icon">
                        <i class="ri-file-list-3-line"></i>
                    </span>
                    <span class="nav-text">Órdenes de Compra</span>
                </a>
            </li>
            @endif

            

            @canany(['invoices.view', 'invoice_intake.view'])
            <li class="nav-item">
                <a class="nav-link menu-arrow {{ request()->routeIs('invoices.*', 'payments.alta_facturas') ? 'active' : '' }}" href="#sidebarInvoices" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('invoices.*', 'payments.alta_facturas') ? 'true' : 'false' }}" aria-controls="sidebarInvoices">
                    <span class="nav-icon">
                        <i class="ri-file-list-3-line"></i>
                    </span>
                    <span class="nav-text">Facturas</span>
                </a>
                <div class="collapse {{ request()->routeIs('invoices.*', 'payments.alta_facturas') ? 'show' : '' }}" id="sidebarInvoices">
                    <ul class="nav sub-navbar-nav">
                        @can('invoice_intake.view')
                        <li class="sub-nav-item">
                            <a class="sub-nav-link {{ request()->routeIs('payments.alta_facturas') ? 'active' : '' }}" href="{{ route('payments.alta_facturas') }}">Alta de facturas</a>
                        </li>
                        @endcan
                        @can('invoices.view')
                        <li class="sub-nav-item">
                            <a class="sub-nav-link {{ request()->routeIs('invoices.*') && request('payment_condition') === 'contado' ? 'active' : '' }}" href="{{ route('invoices.index', ['payment_condition' => 'contado']) }}">Contado</a>
                        </li>
                        <li class="sub-nav-item">
                            <a class="sub-nav-link {{ request()->routeIs('invoices.*') && request('payment_condition') === 'credito' ? 'active' : '' }}" href="{{ route('invoices.index', ['payment_condition' => 'credito']) }}">Crédito</a>
                        </li>
                        <li class="sub-nav-item">
                            <a class="sub-nav-link {{ request()->routeIs('invoices.*') && !in_array(request('payment_condition'), ['contado', 'credito'], true) ? 'active' : '' }}" href="{{ route('invoices.index') }}">Ver Todas</a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcanany

            @canany(['payment_authorizations.view', 'payments_payable.view', 'payments_paid.view'])
            <li class="menu-title">Pagos</li>
                @can('payment_authorizations.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('payments.index') || request()->routeIs('payments.interactive') ? 'active' : '' }}"
                    href="{{ route('payments.index') }}">
                        <span class="nav-icon">
                            <i class="ri-shield-check-line"></i>
                        </span>
                        <span class="nav-text">Autorizaciones</span>
                        @if(($paymentsToAuthorizeCount ?? 0) > 0)
                            <span class="badge bg-warning badge-pill">{{ $paymentsToAuthorizeCount }}</span>
                        @endif
                    </a>
                </li>
                @endcan

                @can('payments_payable.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('payments.payable') ? 'active' : '' }}"
                    href="{{ route('payments.payable') }}">
                        <span class="nav-icon">
                            <i class="ri-money-dollar-circle-line"></i>
                        </span>
                        <span class="nav-text">Por Pagar</span>
                        @if(($paymentsToPayCount ?? 0) > 0)
                            <span class="badge bg-info badge-pill">{{ $paymentsToPayCount }}</span>
                        @endif
                    </a>
                </li>
                @endcan

                @can('payments_paid.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('payments.paid') ? 'active' : '' }}" href="{{ route('payments.paid') }}">
                        <span class="nav-icon">
                            <i class="ri-file-chart-line"></i>
                        </span>
                        <span class="nav-text">Pagado</span>
                    </a>
                </li>
                @endcan
            @endcanany

            @canany(['concepts.view', 'awarded_prices.view', 'concept_categories.view', 'users.view', 'notifications_audit.view'])
            <li class="menu-title">Configuración</li>

                @canany(['concepts.view', 'awarded_prices.view', 'concept_categories.view'])

                <li class="nav-item">
                    <a class="nav-link menu-arrow {{ request()->routeIs('concepts.*') || request()->routeIs('concept_categories.*') ? 'active' : '' }}" href="#sidebarConcepts" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarConcepts">
                        <span class="nav-icon">
                            <i class="ri-pages-line"></i>
                        </span>
                        <span class="nav-text"> Suministros </span>
                    </a>
                    <div class="collapse" id="sidebarConcepts">
                        <ul class="nav sub-navbar-nav">
                            @can('concepts.view')
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('concepts.*') ? 'active' : '' }}" href="{{ route('concepts.index') }}">Conceptos</a>
                            </li>
                            @endcan
                            @can('awarded_prices.view')
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('concepts.awarded_prices') ? 'active' : '' }}" href="{{ route('concepts.awarded_prices') }}">Precios adjudicados</a>
                            </li>
                            @endcan
                            @can('concept_categories.view')
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('concept_categories.*') ? 'active' : '' }}" href="{{ route('concept_categories.index') }}">Familias</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcanany

                @can('users.view')
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('usuarios.index') }}">
                        <span class="nav-icon">
                            <i class="ri-pages-line"></i>
                        </span>
                        <span class="nav-text">Usuarios</span>
                    </a>
                </li>
                @endcan

                @can('notifications_audit.view')
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('notifications.index') }}">
                        <span class="nav-icon">
                            <i class="ri-shapes-line"></i>
                        </span>
                        <span class="nav-text">Notificaciones</span>
                        <span class="badge bg-danger badge-pill text-end">Hot</span>
                    </a>
                </li>
                @endcan
            @endcanany
        </ul>
    </div>
</div>
<!-- ========== App Menu End ========== -->