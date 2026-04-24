<header class="">
    <div class="topbar">
    <div class="container-fluid">
        <div class="navbar-header">
                <div class="d-flex align-items-center gap-2">
                    <!-- Menu Toggle Button -->
                    <div class="topbar-item">
                        <button type="button" class="button-toggle-menu topbar-button">
                            <i class="ri-menu-2-line fs-24"></i>
                        </button>
                    </div>

                    <!-- App Search-->
                    {{--  
                    <form class="app-search d-none d-md-block me-auto">
                        <div class="position-relative">
                            <input type="search" class="form-control border-0" placeholder="Search..." autocomplete="off" value="">
                            <i class="ri-search-line search-widget-icon"></i>
                        </div>
                    </form>
                    --}}
                </div>

                <div class="d-flex align-items-center gap-1">
                    <!-- Theme Color (Light/Dark) -->
                    <div class="topbar-item">
                        <button type="button" class="topbar-button" id="light-dark-mode">
                            <i class="ri-moon-line fs-24 light-mode"></i>
                            <i class="ri-sun-line fs-24 dark-mode"></i>
                        </button>
                    </div>

                    <!-- Category -->
                    <div class="dropdown topbar-item d-none d-lg-flex">
                        <button type="button" class="topbar-button" data-toggle="fullscreen">
                            <i class="ri-fullscreen-line fs-24 fullscreen"></i>
                            <i class="ri-fullscreen-exit-line fs-24 quit-fullscreen"></i>
                        </button>
                    </div>

                    <!-- Notification -->
                    <div class="dropdown topbar-item">
                        <button type="button" class="topbar-button position-relative" id="page-header-notifications-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="ri-notification-3-line fs-24"></i>
                            @if ($topbarUnreadCount > 0)
                                <span id="notif-badge" class="position-absolute topbar-badge fs-10 translate-middle badge bg-danger rounded-pill">
                                    {{ $topbarUnreadCount > 99 ? '99+' : $topbarUnreadCount }}
                                    <span class="visually-hidden">notificaciones sin leer</span>
                                </span>
                            @else
                                <span id="notif-badge" class="d-none position-absolute topbar-badge fs-10 translate-middle badge bg-danger rounded-pill"></span>
                            @endif
                        </button>
                        <div class="dropdown-menu py-0 dropdown-lg dropdown-menu-end" aria-labelledby="page-header-notifications-dropdown">
                            <div class="p-3 border-top-0 border-start-0 border-end-0 border-dashed border">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="m-0 fs-16 fw-semibold">Notificaciones</h6>
                                    </div>
                                    <div class="col-auto">
                                        <button id="btn-mark-read" type="button" class="btn btn-link p-0 text-dark text-decoration-underline fs-12"
                                            data-url="{{ route('notifications.markAllRead') }}">
                                            <small>Marcar como leídas</small>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="notif-list" data-simplebar style="max-height: 280px;">
                                @forelse ($topbarNotifications as $notif)
                                    <a href="{{ route('notifications.index') }}" class="dropdown-item py-3 border-bottom text-wrap">
                                        <div class="d-flex align-items-start gap-2">
                                            <div class="avatar-xs bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-1">
                                                @php
                                                    $actionIcons = [
                                                        'create' => ['icon' => 'ri-add-line',       'color' => 'success'],
                                                        'update' => ['icon' => 'ri-edit-line',       'color' => 'warning'],
                                                        'delete' => ['icon' => 'ri-delete-bin-line', 'color' => 'danger'],
                                                    ];
                                                    $ai = $actionIcons[$notif->model_action] ?? ['icon' => 'ri-information-line', 'color' => 'secondary'];
                                                @endphp
                                                <i class="{{ $ai['icon'] }} text-{{ $ai['color'] }} fs-14"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <p class="mb-0 fs-13">
                                                    <span class="fw-medium">{{ $notif->user?->name ?? 'Sistema' }}</span>
                                                    {{ $notif->data }}
                                                </p>
                                                <span class="text-muted fs-11">{{ $notif->created_at->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                    </a>
                                @empty
                                    <div id="notif-empty" class="text-center text-muted py-4">
                                        <i class="ri-checkbox-circle-line fs-24 d-block mb-1"></i>
                                        <span class="fs-13">Sin notificaciones pendientes</span>
                                    </div>
                                @endforelse
                            </div>

                            <div class="text-center py-3">
                                <a href="{{ route('notifications.index') }}" class="btn btn-primary btn-sm">
                                    Ver toda la actividad <i class="ri-arrow-right-line ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Theme Setting -->
                    <div class="topbar-item d-none d-md-flex">
                        <button type="button" class="topbar-button" id="theme-settings-btn" data-bs-toggle="offcanvas" data-bs-target="#theme-settings-offcanvas" aria-controls="theme-settings-offcanvas">
                            <i class="ri-settings-4-line fs-24"></i>
                        </button>
                    </div>

                    <!-- User -->
                    <div class="dropdown topbar-item">
                        <a type="button" class="topbar-button" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="d-flex align-items-center">
                                @php
                                    $gravatarHash = md5(strtolower(trim(Auth::user()->email)));
                                    $gravatarUrl  = "https://www.gravatar.com/avatar/{$gravatarHash}?s=64&d=identicon";
                                @endphp
                                <img class="rounded-circle" width="32" height="32"
                                     src="{{ $gravatarUrl }}"
                                     alt="{{ Auth::user()->name }}">
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <h6 class="dropdown-header">{{ Auth::user()->name }}</h6>

                            <a class="dropdown-item" href="{{ route('usuarios.show', Auth::user()) }}">
                                <iconify-icon icon="solar:settings-broken" class="align-middle me-2 fs-18"></iconify-icon>
                                <span class="align-middle">Configuración</span>
                            </a>

                            <div class="dropdown-divider my-1"></div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger w-100 text-start">
                                    <iconify-icon icon="solar:logout-3-broken" class="align-middle me-2 fs-18"></iconify-icon>
                                    <span class="align-middle">Cerrar Sesión</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
    </div></div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btn-mark-read');
    if (!btn) return;

    btn.addEventListener('click', function () {
        fetch(btn.dataset.url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            // Vaciar lista y mostrar estado vacío
            const list = document.getElementById('notif-list');
            list.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="ri-checkbox-circle-line fs-24 d-block mb-1"></i>
                    <span class="fs-13">Sin notificaciones pendientes</span>
                </div>`;

            // Ocultar badge
            const badge = document.getElementById('notif-badge');
            badge.classList.add('d-none');
            badge.textContent = '';
        });
    });
});
</script>