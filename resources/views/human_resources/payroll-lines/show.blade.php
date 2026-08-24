@extends('layouts.app')

@section('page_title', 'Detalle de nómina')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('human_resources.payroll-lines.index', ['payroll_period_id' => $payrollLine->payroll_period_id]) }}">Líneas de nómina</a></li>
    <li class="breadcrumb-item active">{{ $payrollLine->worker->first_name }} {{ $payrollLine->worker->last_name }}</li>
@endsection

@section('content')
@include('human_resources.partials.flash')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <div>
                    <h5 class="card-title mb-0">{{ $payrollLine->worker->first_name }} {{ $payrollLine->worker->last_name }}</h5>
                    <span class="text-muted fs-12">Semana {{ $payrollLine->payrollPeriod->week_number }}/{{ $payrollLine->payrollPeriod->year }} · {{ $payrollLine->positionCategory?->name }}</span>
                </div>
                <strong class="fs-5">${{ number_format($payrollLine->total_amount, 2) }}</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">Sueldo base <strong class="float-end">${{ number_format($payrollLine->base_salary, 2) }}</strong></div>
                    <div class="col-6">Comida ({{ $payrollLine->meal_days }} días) <strong class="float-end">${{ number_format($payrollLine->meal_total_amount, 2) }}</strong></div>
                    <div class="col-6">Domingo <strong class="float-end">${{ number_format($payrollLine->sunday_amount, 2) }}</strong></div>
                    <div class="col-6">Incentivos <strong class="float-end">${{ number_format($payrollLine->incentive_amount, 2) }}</strong></div>
                    <div class="col-6">Inasistencias ({{ $payrollLine->absence_days }}) <strong class="float-end text-danger">-${{ number_format($payrollLine->absence_amount, 2) }}</strong></div>
                    <div class="col-6">Complemento <strong class="float-end">${{ number_format($payrollLine->complement_amount, 2) }}</strong></div>
                </div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><h5 class="card-title mb-0">Asistencias vinculadas</h5></div>
            <div class="card-body"><div class="d-flex flex-wrap gap-2">@foreach($payrollLine->attendances as $attendance)<span class="badge {{ $attendance->attended ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $attendance->date->format('D d/m') }}: {{ $attendance->attended ? 'Asistió' : 'Falta' }}</span>@endforeach</div></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Ajustes manuales</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('human_resources.payroll-lines.update', $payrollLine) }}">
                    @csrf
                    @method('PUT')
                    @foreach(['lost_material_amount' => 'Material extraviado', 'loan_amount' => 'Préstamo', 'infonavit_amount' => 'INFONAVIT', 'savings_fund_amount' => 'Caja de ahorro', 'extras_amount' => 'Extras', 'fiscal_amount' => 'Fiscal'] as $field => $label)
                        <label class="form-label mt-2">{{ $label }}</label>
                        <input class="form-control" name="{{ $field }}" type="number" min="0" step="0.01" value="{{ $payrollLine->$field }}" @disabled($payrollLine->payrollPeriod->status !== 'open')>
                    @endforeach
                    <button class="btn btn-primary w-100 mt-3" @disabled($payrollLine->payrollPeriod->status !== 'open')>Recalcular ajustes</button>
                </form>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><h5 class="card-title mb-0">Incentivos aplicados</h5></div>
            <div class="card-body">
                @forelse($payrollLine->extraPayments as $extraPayment)
                    @php($categoryLabel = ['overtime' => 'Tiempo extra', 'day_off_exchange' => 'Libranza', 'emergency' => 'Emergencia'][$extraPayment->incentive->category])
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="fw-medium">{{ $categoryLabel }} · Tipo {{ $extraPayment->incentive->rate_type }}</div>
                            <span class="text-muted fs-12">{{ $extraPayment->incentive->incentive_date->format('d/m/Y') }}{{ $extraPayment->incentive->notes ? ' · '.$extraPayment->incentive->notes : '' }}</span>
                        </div>
                        <strong>${{ number_format($extraPayment->amount, 2) }}</strong>
                    </div>
                @empty
                    <span class="text-muted">El trabajador no tuvo incentivos activos dentro de este periodo.</span>
                @endforelse
                @if($payrollLine->payrollPeriod->status === 'open')
                    <a class="btn btn-outline-primary btn-sm w-100 mt-2" href="{{ route('human_resources.workers.show', $payrollLine->worker) }}"><i class="ri-medal-line me-1"></i>Gestionar incentivos del trabajador</a>
                    <form method="POST" action="{{ route('human_resources.payroll-lines.rebuild', $payrollLine) }}" class="mt-2">@csrf<button class="btn btn-outline-secondary btn-sm w-100">Sincronizar desde trabajador</button></form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection