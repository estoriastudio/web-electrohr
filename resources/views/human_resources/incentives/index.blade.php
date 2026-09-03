@extends('layouts.app')

@section('page_title', 'Incentivos')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Incentivos</li>
@endsection

@section('content')
@include('human_resources.partials.flash')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Asignar incentivo</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('human_resources.incentives.store') }}">
                    @csrf
                    @include('human_resources.incentives._form', ['incentive' => $formIncentive])
                    <button class="btn btn-primary w-100 mt-3">Guardar incentivo</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
                <div>
                    <h5 class="card-title mb-0">Historial de incentivos</h5>
                    <span class="text-muted fs-12">El importe se calcula según el concepto registrado.</span>
                </div>
                <form method="GET">
                    <select name="worker_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Todos los trabajadores</option>
                        @foreach($workers as $worker)
                            <option value="{{ $worker->id }}" @selected((string) $workerId === (string) $worker->id)>{{ $worker->last_name }}, {{ $worker->first_name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light-subtle"><tr><th>Trabajador</th><th>Concepto</th><th>Fecha</th><th>Estatus</th><th>Notas</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                        @forelse($incentives as $incentive)
                            @php($categoryLabel = ['incentive' => 'Incentivo', 'overtime' => 'Horas extra', 'day_off_exchange' => 'Libranza', 'emergency' => 'Emergencia'][$incentive->category] ?? $incentive->category)
                            <tr>
                                <td><a class="text-dark fw-medium" href="{{ route('human_resources.workers.show', $incentive->worker) }}">{{ $incentive->worker->first_name }} {{ $incentive->worker->last_name }}</a></td>
                                <td>{{ $categoryLabel }} @if($incentive->category === 'overtime' && $incentive->overtime_hours !== null)<span class="badge bg-light text-dark border">{{ $incentive->overtime_hours }} h x ${{ number_format((float) $incentive->overtime_hourly_rate, 2) }}</span>@else<span class="badge bg-light text-dark border">{{ $incentive->rate_type }}</span>@endif</td>
                                <td>{{ $incentive->incentive_date->format('d/m/Y') }}</td>
                                <td><span class="badge {{ $incentive->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $incentive->status === 'active' ? 'Activo' : 'Cancelado' }}</span></td>
                                <td class="text-muted">{{ $incentive->notes ?: '—' }}</td>
                                <td class="text-end">
                                    <form class="d-inline" method="POST" action="{{ route('human_resources.incentives.destroy', $incentive) }}" onsubmit="return confirm('¿Eliminar este incentivo?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-light btn-sm text-danger" title="Eliminar"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No hay incentivos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($incentives->hasPages())<div class="card-footer d-flex justify-content-end">{{ $incentives->links('pagination::bootstrap-5') }}</div>@endif
        </div>
    </div>
</div>
@endsection