@extends('layouts.app')

@section('page_title', 'Control de Herramientas')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tools.index') }}">Herramientas</a></li>
    <li class="breadcrumb-item active">Control de Herramientas</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-add-circle-line me-1 text-primary"></i> Nuevo Registro de Control</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('tool_controls.store') }}" method="POST" id="tool-control-form">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Herramienta <span class="text-danger">*</span></label>
                        <select name="tool_id" class="form-select js-tool-select @error('tool_id') is-invalid @enderror" required>
                            <option value="">Buscar y seleccionar herramienta...</option>
                            @foreach ($tools as $tool)
                                <option value="{{ $tool->id }}" @selected((string) old('tool_id') === (string) $tool->id)>
                                    {{ $tool->economic_number }} · {{ $tool->description }}
                                </option>
                            @endforeach
                        </select>
                        @error('tool_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Obra <span class="text-danger">*</span></label>
                        <select name="project_work_id" class="form-select js-work-select @error('project_work_id') is-invalid @enderror" required>
                            <option value="">Buscar y seleccionar una obra...</option>
                            @foreach ($projectWorks as $projectWork)
                                <option value="{{ $projectWork->id }}" @selected((string) old('project_work_id') === (string) $projectWork->id)>
                                    {{ $projectWork->project?->name ?? 'Sin proyecto' }} · {{ $projectWork->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('project_work_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-2">
                        <div class="col-md-12">
                            <label class="form-label">Responsable <span class="text-danger">*</span></label>
                            <input type="text" name="responsible" class="form-control @error('responsible') is-invalid @enderror" value="{{ old('responsible') }}" maxlength="150" required>
                            @error('responsible') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo de préstamo <span class="text-danger">*</span></label>
                            <select name="loan_type" class="form-select @error('loan_type') is-invalid @enderror" required>
                                <option value="fixed" @selected(old('loan_type', 'fixed') === 'fixed')>Fijo</option>
                                <option value="provisional" @selected(old('loan_type') === 'provisional')>Provisional</option>
                            </select>
                            @error('loan_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estatus <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Activo</option>
                                <option value="closed" @selected(old('status') === 'closed')>Cerrado</option>
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de salida <span class="text-danger">*</span></label>
                            <input type="date" name="checkout_date" class="form-control @error('checkout_date') is-invalid @enderror" value="{{ old('checkout_date', now()->format('Y-m-d')) }}" required>
                            @error('checkout_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de revisión</label>
                            <input type="date" name="review_date" class="form-control @error('review_date') is-invalid @enderror" value="{{ old('review_date') }}">
                            @error('review_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observations_text" class="form-control @error('observations_text') is-invalid @enderror" rows="3" maxlength="2000" placeholder="Notas opcionales sobre el uso de la herramienta...">{{ old('observations_text') }}</textarea>
                        @error('observations_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mt-3 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-line me-1"></i> Guardar Control
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card">
            <div class="card-header border-bottom">
                <form method="GET" action="{{ route('tool_controls.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label mb-1">Buscar</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Herramienta, proyecto u obra..." value="{{ $search }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Tipo</label>
                        <select name="loan_type" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="fixed" @selected($loanType === 'fixed')>Fijo</option>
                            <option value="provisional" @selected($loanType === 'provisional')>Provisional</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Estatus</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="active" @selected($status === 'active')>Activo</option>
                            <option value="closed" @selected($status === 'closed')>Cerrado</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if($search || $loanType || $status)
                            <a href="{{ route('tool_controls.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Herramienta</th>
                                <th>Proyecto / Obra</th>
                                <th>Préstamo</th>
                                <th>Responsable</th>
                                <th>Salida</th>
                                <th>Revisión</th>
                                <th>Estatus</th>
                                <th class="text-center">Detalle</th>
                                <th class="text-end pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($controls as $control)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $control->tool?->economic_number ?? 'N/D' }}</div>
                                        <div class="text-muted fs-12">{{ $control->tool?->name ?? 'Herramienta eliminada' }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-medium">{{ $control->projectWork?->project?->name ?? 'Sin proyecto' }}</div>
                                        <div class="text-muted fs-12">{{ $control->projectWork?->name ?? 'Sin obra' }}</div>
                                    </td>
                                    <td>{{ $control->loan_type === 'fixed' ? 'Fijo' : 'Provisional' }}</td>
                                    <td>{{ $control->responsible ?: '—' }}</td>
                                    <td>{{ $control->checkout_date?->format('Y-m-d') }}</td>
                                    <td>{{ $control->review_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>
                                        @if($control->status === 'active')
                                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Activo</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Cerrado</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($control->tool)
                                            <a href="{{ route('tools.show', $control->tool) }}"
                                               class="btn btn-soft-info btn-sm"
                                               title="Ver detalle de herramienta">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <button type="button"
                                                    class="btn btn-soft-primary btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalEditControl{{ $control->id }}"
                                                    title="Editar">
                                                <i class="ri-edit-line"></i>
                                            </button>
                                            <form action="{{ route('tool_controls.destroy', $control) }}" method="POST" onsubmit="return confirm('¿Eliminar este control?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="ri-calendar-event-line fs-24 d-block mb-1 opacity-50"></i>
                                        No hay registros de control.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($controls->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $controls->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

@foreach($controls as $control)
    @php
        $obsText = is_array($control->observations) && count($control->observations)
            ? ($control->observations[0]['note'] ?? '')
            : '';
    @endphp
    <div class="modal fade" id="modalEditControl{{ $control->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('tool_controls.update', $control) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ri-edit-line me-1"></i> Editar Control</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2">
                            <strong>{{ $control->tool?->economic_number }}</strong> · {{ $control->tool?->description }}
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Obra <span class="text-danger">*</span></label>
                            <select name="project_work_id" class="form-select js-work-select" required>
                                @foreach ($projectWorks as $projectWork)
                                    <option value="{{ $projectWork->id }}" @selected((int)$control->project_work_id === (int)$projectWork->id)>
                                        {{ $projectWork->project?->name ?? 'Sin proyecto' }} · {{ $projectWork->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Responsable <span class="text-danger">*</span></label>
                                <input type="text" name="responsible" class="form-control" value="{{ $control->responsible }}" maxlength="150" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tipo de préstamo <span class="text-danger">*</span></label>
                                <select name="loan_type" class="form-select" required>
                                    <option value="fixed" @selected($control->loan_type === 'fixed')>Fijo</option>
                                    <option value="provisional" @selected($control->loan_type === 'provisional')>Provisional</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estatus <span class="text-danger">*</span></label>
                                <select name="status" class="form-select" required>
                                    <option value="active" @selected($control->status === 'active')>Activo</option>
                                    <option value="closed" @selected($control->status === 'closed')>Cerrado</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de salida <span class="text-danger">*</span></label>
                                <input type="date" name="checkout_date" class="form-control" value="{{ $control->checkout_date?->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de revisión</label>
                                <input type="date" name="review_date" class="form-control" value="{{ $control->review_date?->format('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observations_text" class="form-control" rows="3" maxlength="2000">{{ $obsText }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

@endsection

@push('scripts')
<script>
(function () {
    function initChoices(selector, placeholder) {
        document.querySelectorAll(selector).forEach(function (element) {
            if (element.dataset.choicesReady === '1') {
                return;
            }

            new Choices(element, {
                searchEnabled: true,
                itemSelectText: '',
                searchPlaceholderValue: placeholder,
                noResultsText: 'Sin resultados',
                noChoicesText: 'Sin opciones disponibles',
                shouldSort: false,
                maxItemCount: 1,
            });

            element.dataset.choicesReady = '1';
        });
    }

    initChoices('.js-tool-select', 'Buscar herramienta...');
    initChoices('.js-work-select', 'Buscar obra...');
}());
</script>
@endpush
