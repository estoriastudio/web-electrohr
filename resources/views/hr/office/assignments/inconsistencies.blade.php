@extends('layouts.app')

@section('page_title', 'Incongruencias')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item">Recursos Humanos</li>
    <li class="breadcrumb-item active">Incongruencias</li>
@endsection

@section('content')
    @include('partials.flash')
    @include('hr.office.partials.inbox-tabs', ['active' => 'inconsistencies'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
            <div>
                <h4 class="card-title mb-0">Activo sin asignación</h4>
                <span class="text-muted fs-12">Todo el personal activo arranca Sin asignación hasta que RH lo asigna o se aprueba una solicitud.</span>
            </div>
            <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">{{ $unassigned->count() }}</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                    <thead class="bg-light-subtle">
                        <tr><th>Persona</th><th>Cuadrilla</th><th>Cargo</th><th>Asignar</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($unassigned as $worker)
                            <tr>
                                <td>{{ $worker->last_name }}, {{ $worker->first_name }}<div class="text-muted fs-12">NSS {{ $worker->nss }}</div></td>
                                <td>{{ $worker->workerGroup?->name ?? '—' }}</td>
                                <td>{{ $worker->positionCategory?->name ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('rh.office.assignments.assign') }}" class="d-flex gap-2 align-items-start">
                                        @csrf
                                        <input type="hidden" name="worker_id" value="{{ $worker->id }}">
                                        <select name="project_work_id" class="form-select form-select-sm" required style="min-width: 200px;">
                                            <option value="">Obra…</option>
                                            @foreach ($projectWorks as $work)
                                                <option value="{{ $work->id }}" @selected((string) old('project_work_id') === (string) $work->id && (string) old('worker_id') === (string) $worker->id)>{{ $work->name }} — {{ $work->project?->name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="date" name="effective_date" class="form-control form-control-sm" style="width: 150px;"
                                               value="{{ old('worker_id') == $worker->id ? old('effective_date') : now()->toDateString() }}"
                                               min="{{ $minimumDates[$worker->id] }}" max="{{ now()->toDateString() }}" required>
                                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Motivo" maxlength="500" required
                                               value="{{ old('worker_id') == $worker->id ? old('reason') : '' }}">
                                        <button type="submit" class="btn btn-sm btn-primary">Asignar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No hay personal activo sin asignación.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
            <div>
                <h4 class="card-title mb-0">Solicitado por más de una Obra</h4>
                <span class="text-muted fs-12">Elige la Obra que se queda con la persona; las demás solicitudes se rechazan.</span>
            </div>
            <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">{{ $multiple->count() }}</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                    <thead class="bg-light-subtle">
                        <tr><th>Persona</th><th>Solicitudes</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($multiple as $lines)
                            <tr>
                                <td>{{ $lines->first()->worker?->last_name }}, {{ $lines->first()->worker?->first_name }}</td>
                                <td>
                                    @foreach ($lines as $line)
                                        <form method="POST" action="{{ route('rh.office.assignments.assign-here', $line) }}" class="d-flex align-items-center gap-2 mb-1"
                                              onsubmit="return confirm('¿Asignar a {{ $line->personnelRequest->projectWork?->name }} y rechazar las demás solicitudes?')">
                                            @csrf
                                            <span>{{ $line->personnelRequest->projectWork?->name }} <span class="text-muted fs-12">({{ $line->personnelRequest->projectWork?->project?->name }})</span></span>
                                            <button type="submit" class="btn btn-sm btn-soft-primary">Asignar aquí</button>
                                        </form>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted py-4">No hay personas solicitadas por más de una Obra.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
