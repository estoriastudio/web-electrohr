@extends('layouts.app')

@section('page_title', 'Auditoría')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Auditoría</li>
@endsection

@section('content')

<div class="row">
    <div class="col-xl-12">

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ri-line-chart-line text-primary fs-18"></i>
                            </div>
                            <span class="text-muted fs-12 fw-medium">Acciones promedio por día</span>
                        </div>
                        <h3 class="fw-bold mb-0">{{ number_format($activityStats['daily_average'], 1) }}</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="avatar-sm bg-success bg-opacity-10 rounded d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ri-calendar-check-line text-success fs-18"></i>
                            </div>
                            <span class="text-muted fs-12 fw-medium">Acciones de la semana en curso</span>
                        </div>
                        <h3 class="fw-bold mb-0">{{ number_format($activityStats['current_week']) }}</h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="avatar-sm bg-warning bg-opacity-10 rounded d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ri-trophy-line text-warning fs-18"></i>
                            </div>
                            <span class="text-muted fs-12 fw-medium">Usuario con más acciones</span>
                        </div>

                        @if ($activityStats['leaders']->isNotEmpty())
                            @php($leader = $activityStats['leaders']->first())
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                        <span class="text-warning fw-semibold">{{ strtoupper(substr($leader->user?->name ?? '?', 0, 1)) }}</span>
                                    </div>
                                    <span class="fw-semibold text-truncate">{{ $leader->user?->name ?? 'Usuario eliminado' }}</span>
                                </div>
                                <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12 text-nowrap">{{ number_format($leader->actions_count) }} acciones</span>
                            </div>

                            @if ($activityStats['leaders']->count() > 1)
                                <div class="border-top mt-3 pt-2">
                                    @foreach ($activityStats['leaders']->skip(1) as $leaderboardUser)
                                        <div class="d-flex align-items-center justify-content-between gap-2 py-1 fs-13">
                                            <span class="text-muted text-truncate">{{ $leaderboardUser->user?->name ?? 'Usuario eliminado' }}</span>
                                            <span class="fw-medium text-nowrap">{{ number_format($leaderboardUser->actions_count) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <span class="text-muted fs-13">Sin acciones registradas</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Filtros --}}
        @include('notifications.utilities._search_options')

        <div class="card mt-3">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Registro de actividad</h4>
                <span class="text-muted fs-13">{{ $notifications->total() }} registros</span>
            </div>
            <div class="card-body p-0">
                @include('notifications.utilities._table')
            </div>
            <div class="card-footer border-top">
                {{ $notifications->links('pagination::bootstrap-5') }}
            </div>
        </div>

    </div>
</div>

@endsection
