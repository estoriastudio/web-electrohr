@extends('layouts.app')

@section('page_title', 'Habilitar captura 24 h')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item">Recursos Humanos</li>
    <li class="breadcrumb-item active">Habilitaciones</li>
@endsection

@section('content')
    @include('partials.flash')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
            <div>
                <h4 class="card-title mb-0">Habilitaciones activas</h4>
                <span class="text-muted fs-12">Abren una fecha de una Obra para el campo durante 24 h. No abren las horas extra.</span>
            </div>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createUnlockModal">
                <i class="ri-add-line me-1"></i> Habilitar captura
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                    <thead class="bg-light-subtle">
                        <tr><th>Obra</th><th>Fecha</th><th>Motivo</th><th>Otorgó</th><th>Vence</th><th>Acción</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($unlocks as $unlock)
                            <tr>
                                <td>{{ $unlock->projectWork?->name }}<div class="text-muted fs-12">{{ $unlock->projectWork?->project?->name }}</div></td>
                                <td>{{ $unlock->date->format('d/m/Y') }}</td>
                                <td class="text-wrap" style="max-width: 280px;">{{ $unlock->reason }}</td>
                                <td>{{ $unlock->grantedByUser?->name ?? '—' }}</td>
                                <td>{{ $unlock->expires_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <form method="POST" action="{{ route('rh.office.unlocks.destroy', $unlock) }}" class="d-inline"
                                          onsubmit="return confirm('¿Retirar esta habilitación?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-soft-danger btn-sm">Retirar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No hay habilitaciones activas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($unlocks->hasPages())<div class="card-footer border-top">{{ $unlocks->links('pagination::bootstrap-5') }}</div>@endif
    </div>

    <div class="card">
        <div class="card-header border-bottom">
            <h4 class="card-title mb-0">Días sin liberar</h4>
            <span class="text-muted fs-12">Obras con padrón que no liberaron un día del periodo en curso.</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                    <thead class="bg-light-subtle">
                        <tr><th>Obra</th><th>Fecha</th><th>Estado</th><th>Acción</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($unreleasedDays as $day)
                            <tr>
                                <td>{{ $day['work']->name }}<div class="text-muted fs-12">{{ $day['work']->project?->name }}</div></td>
                                <td>{{ $day['date']->format('d/m/Y') }}</td>
                                <td><span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Día sin liberar</span></td>
                                <td><a href="{{ route('rh.office.unlocks.index', ['obra' => $day['work']->id, 'fecha' => $day['date']->toDateString()]) }}" class="btn btn-soft-primary btn-sm">Habilitar</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Todos los días anteriores están liberados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createUnlockModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="POST" action="{{ route('rh.office.unlocks.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Habilitar captura 24 h</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="unlock_work">Obra <span class="text-danger">*</span></label>
                            <select id="unlock_work" name="project_work_id" class="form-select @error('project_work_id') is-invalid @enderror" required>
                                <option value="">Selecciona una Obra</option>
                                @foreach ($projectWorks as $work)
                                    <option value="{{ $work->id }}" @selected((string) old('project_work_id', $prefillWork) === (string) $work->id)>{{ $work->name }} — {{ $work->project?->name }}</option>
                                @endforeach
                            </select>
                            @error('project_work_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="unlock_date">Fecha <span class="text-danger">*</span></label>
                            <input id="unlock_date" type="date" name="date" max="{{ now()->subDay()->toDateString() }}" value="{{ old('date', $prefillDate) }}"
                                   class="form-control @error('date') is-invalid @enderror" required>
                            @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="unlock_reason">Motivo <span class="text-danger">*</span></label>
                            <textarea id="unlock_reason" name="reason" rows="3" maxlength="500" class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Habilitar</button>
                </div>
            </form>
        </div>
    </div>

    @if ($errors->any() || $prefillWork)
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('createUnlockModal')).show();
                });
            </script>
        @endpush
    @endif
@endsection
