@extends('layouts.app')

@section('page_title', 'Configuración de RH')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item">Recursos Humanos</li>
    <li class="breadcrumb-item active">Configuración</li>
@endsection

@section('content')
    @include('partials.flash')

    @php
        $dayOffTypes = $settings['day_off_types'] ?? [];
        $sunday = $settings['sunday'] ?? ['divisor' => 6, 'bonus' => 50];
    @endphp

    <form method="POST" action="{{ route('rh.office.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Libranza A / B / C</h4>
                    <span class="text-muted fs-12">Porcentaje del valor del día que paga cada tipo. Vacío = por definir (paga 100 % con aviso).</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Tipo</th>
                                <th style="width: 240px;">Porcentaje (%)</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (['A', 'B', 'C'] as $letter)
                                @php $pct = old("day_off_types.$letter.pct", $dayOffTypes[$letter]['pct'] ?? null); @endphp
                                <tr>
                                    <td class="fw-medium">Libranza {{ $letter }}</td>
                                    <td>
                                        <input type="number" step="0.01" min="0" max="100"
                                               name="day_off_types[{{ $letter }}][pct]" value="{{ $pct }}"
                                               class="form-control form-control-sm @error("day_off_types.$letter.pct") is-invalid @enderror">
                                        @error("day_off_types.$letter.pct")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        @if ($pct === null || $pct === '')
                                            <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Por definir</span>
                                        @else
                                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Definido</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header border-bottom">
                        <h4 class="card-title mb-0">Regla del domingo</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="sunday_divisor">Divisor del sueldo semanal <span class="text-danger">*</span></label>
                                <input id="sunday_divisor" type="number" step="1" min="1" name="sunday[divisor]"
                                       value="{{ old('sunday.divisor', $sunday['divisor'] ?? 6) }}"
                                       class="form-control @error('sunday.divisor') is-invalid @enderror" required>
                                @error('sunday.divisor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="sunday_bonus">Bono (pesos) <span class="text-danger">*</span></label>
                                <input id="sunday_bonus" type="number" step="0.01" min="0" name="sunday[bonus]"
                                       value="{{ old('sunday.bonus', $sunday['bonus'] ?? 50) }}"
                                       class="form-control @error('sunday.bonus') is-invalid @enderror" required>
                                @error('sunday.bonus')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <p class="text-muted fs-12 mt-2 mb-0">Asistencia en domingo = sueldo semanal ÷ divisor + bono.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header border-bottom">
                        <h4 class="card-title mb-0">Horas extra y caja de ahorro</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="ot_deadline_hour">Hora límite de HE (0-23) <span class="text-danger">*</span></label>
                                <input id="ot_deadline_hour" type="number" step="1" min="0" max="23" name="ot_deadline_hour"
                                       value="{{ old('ot_deadline_hour', $settings['ot_deadline_hour'] ?? 10) }}"
                                       class="form-control @error('ot_deadline_hour') is-invalid @enderror" required>
                                @error('ot_deadline_hour')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="savings_payout_date">Fecha de pago de la caja de ahorro <span class="text-danger">*</span></label>
                                <input id="savings_payout_date" type="date" name="savings_payout_date"
                                       value="{{ old('savings_payout_date', $settings['savings_payout_date'] ?? '') }}"
                                       class="form-control @error('savings_payout_date') is-invalid @enderror" required>
                                @error('savings_payout_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <p class="text-muted fs-12 mt-2 mb-0">Las HE de cada día se capturan hasta esta hora del día siguiente (America/Mexico_City).</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-3">
            <button type="submit" class="btn btn-primary">
                <i class="ri-save-line me-1"></i> Guardar configuración
            </button>
        </div>
    </form>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
            <h4 class="card-title mb-0">Cambios recientes</h4>
            <a href="{{ route('rh.shared.audit.index', ['entidad' => 'Configuración']) }}" class="btn btn-sm btn-light">Ver bitácora</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                    <thead class="bg-light-subtle">
                        <tr>
                            <th>Cuándo</th>
                            <th>Quién</th>
                            <th>Ajuste</th>
                            <th>Antes</th>
                            <th>Después</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentChanges as $change)
                            <tr>
                                <td>{{ $change->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $change->user?->name ?? 'Sistema' }}</td>
                                <td>{{ $change->entity_id }}</td>
                                <td class="text-wrap" style="max-width: 260px;">{{ $change->before ?? '—' }}</td>
                                <td class="text-wrap" style="max-width: 260px;">{{ $change->after ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Aún no hay cambios de configuración.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
