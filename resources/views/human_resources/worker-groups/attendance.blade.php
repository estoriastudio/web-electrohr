@extends('layouts.app')

@section('page_title', 'Lista de asistencia')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('human_resources.worker-groups.index') }}">Cuadrillas</a></li>
    <li class="breadcrumb-item"><a href="{{ route('human_resources.worker-groups.show', $workerGroup) }}">{{ $workerGroup->name }}</a></li>
    <li class="breadcrumb-item active">Asistencia</li>
@endsection

@section('content')
@include('human_resources.partials.flash')
@php
    $absenceLabels = [
        'absence' => 'Inasistencia',
        'rest' => 'Descanso',
        'incapacity' => 'Incapacidad',
        'permission' => 'Permiso',
    ];
    $incentiveLabels = [
        'incentive' => 'Incentivo',
        'overtime' => 'Horas extra',
        'day_off_exchange' => 'Libranza',
    ];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-1">{{ $workerGroup->name }}</h4>
        <span class="text-muted">{{ $workerGroup->projectWork?->name }} · Lista de asistencia</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('human_resources.worker-groups.attendance', [$workerGroup, 'date' => $previousDate]) }}" class="btn btn-light" title="Día anterior"><i class="ri-arrow-left-s-line"></i></a>
        <div class="text-center px-2">
            <div class="fw-semibold">{{ $selectedDate->isToday() ? 'Hoy' : $selectedDate->translatedFormat('l') }}</div>
            <div class="text-muted fs-12">{{ $selectedDate->translatedFormat('d \d\e F \d\e Y') }}</div>
        </div>
        @if($canGoNext)
            <a href="{{ route('human_resources.worker-groups.attendance', [$workerGroup, 'date' => $nextDate]) }}" class="btn btn-light" title="Día siguiente"><i class="ri-arrow-right-s-line"></i></a>
        @else
            <button type="button" class="btn btn-light" title="No hay registros futuros" disabled><i class="ri-arrow-right-s-line"></i></button>
        @endif
        <a href="{{ route('human_resources.worker-groups.show', $workerGroup) }}" class="btn btn-outline-secondary"><i class="ri-arrow-left-line me-1"></i>Cuadrilla</a>
    </div>
</div>

<div id="workerGroupAttendanceFeedback" class="alert alert-danger d-none" role="alert"></div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <div>
            <h5 class="card-title mb-0">Personal asignado</h5>
            <span class="text-muted fs-12">Selecciona el estatus de cada trabajador.</span>
        </div>
        <span class="badge bg-primary-subtle text-primary">{{ $members->count() }} trabajador(es)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light-subtle"><tr><th>Trabajador</th><th>Apodo</th><th>Puesto</th><th>Estatus</th><th>Incentivos</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
                @forelse($members as $member)
                    @php
                        $attendance = $attendances->get($member->id);
                        $memberIncentives = $incentives->get($member->id, collect());
                        $statusLabel = ! $attendance ? 'Sin registrar' : ($attendance->attended ? 'Show' : ($absenceLabels[$attendance->absence_reason] ?? 'No show'));
                        $statusClass = ! $attendance ? 'bg-secondary-subtle text-secondary' : ($attendance->attended ? 'bg-success-subtle text-success' : ($attendance->absence_reason === 'incapacity' ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger'));
                    @endphp
                    <tr class="worker-group-attendance-row" data-worker-id="{{ $member->id }}" data-worker-name="{{ $member->first_name }} {{ $member->last_name }}">
                        <td><a href="{{ route('human_resources.workers.show', $member) }}" class="text-dark fw-medium">{{ $member->first_name }} {{ $member->last_name }}</a></td>
                        <td>{{ $member->nickname ?: '—' }}</td>
                        <td>{{ $member->positionCategory?->name ?: '—' }}</td>
                        <td><span class="js-attendance-status badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <div class="js-worker-incentives d-flex flex-wrap gap-1">
                                    @forelse($memberIncentives as $incentive)
                                        <span class="badge bg-info-subtle text-info" title="{{ $incentiveLabels[$incentive->category] }}">{{ $incentiveLabels[$incentive->category] }} {{ $incentive->category === 'overtime' && $incentive->overtime_hours !== null ? number_format((float) $incentive->overtime_hours, 2).' h x $'.number_format((float) $incentive->overtime_hourly_rate, 2) : $incentive->rate_type }}</span>
                                    @empty
                                        <span class="js-no-incentives text-muted fs-12">—</span>
                                    @endforelse
                                </div>
                                @if($canAddIncentives)
                                    <button type="button" class="btn btn-light btn-sm js-add-incentive" title="Registrar incentivo" aria-label="Registrar incentivo para {{ $member->first_name }} {{ $member->last_name }}"><i class="ri-add-line"></i></button>
                                @endif
                            </div>
                        </td>
                        <td class="text-end">
                            <div class="btn-group" role="group" aria-label="Asistencia de {{ $member->first_name }} {{ $member->last_name }}">
                                <button type="button" class="btn btn-sm js-mark-attendance {{ $attendance?->attended ? 'btn-success' : 'btn-outline-success' }}" data-attended="1"><i class="ri-check-line me-1"></i>Show</button>
                                <button type="button" class="btn btn-sm js-report-absence {{ $attendance && ! $attendance->attended ? 'btn-danger' : 'btn-outline-danger' }}"><i class="ri-close-line me-1"></i>No show</button>
                            </div>
                            @if($attendance?->absence_document_path)<a class="btn btn-light btn-sm ms-1" href="{{ route('human_resources.worker-attendances.absence-document', $attendance) }}" title="Ver comprobante"><i class="ri-file-download-line"></i></a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay trabajadores asignados a la cuadrilla para esta fecha.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="reportAbsenceModal" tabindex="-1" aria-labelledby="reportAbsenceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="reportAbsenceForm" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header"><h5 class="modal-title" id="reportAbsenceModalLabel">Reportar inasistencia</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p class="text-muted mb-3" id="reportAbsenceWorkerName"></p><div class="mb-3"><label class="form-label" for="absence_reason">Motivo</label><select id="absence_reason" name="absence_reason" class="form-select" required><option value="">Selecciona un motivo</option><option value="absence">Inasistencia</option><option value="rest">Descanso</option><option value="incapacity">Incapacidad</option><option value="permission">Permiso</option></select></div><div><label class="form-label" for="absence_document">Comprobante <span class="text-muted fw-normal">(opcional)</span></label><input id="absence_document" name="absence_document" type="file" accept=".pdf,.jpg,.jpeg,.png" class="form-control"></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Reportar</button></div>
        </form>
    </div>
</div>

@if($canAddIncentives)
<div class="modal fade" id="registerAttendanceIncentiveModal" tabindex="-1" aria-labelledby="registerAttendanceIncentiveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="registerAttendanceIncentiveForm" class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="registerAttendanceIncentiveModalLabel">Registrar incentivo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="registerAttendanceIncentiveWorkerName"></p>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="attendanceIncentiveCategory">Concepto <span class="text-danger">*</span></label>
                        <select id="attendanceIncentiveCategory" class="form-select" name="category" required>
                            <option value="">Selecciona un concepto</option>
                            <option value="incentive">Incentivo</option>
                            <option value="overtime">Horas extra</option>
                            <option value="day_off_exchange">Libranza</option>
                        </select>
                    </div>
                    <div class="col-md-5 d-none" data-incentive-rate-field>
                        <label class="form-label" for="attendanceIncentiveRateType">Tipo <span class="text-danger">*</span></label>
                        <select id="attendanceIncentiveRateType" class="form-select" name="rate_type" required><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select>
                    </div>
                    <div class="col-md-6 d-none" data-overtime-field>
                        <label class="form-label" for="attendanceIncentiveOvertimeHours">Cantidad de horas extra <span class="text-danger">*</span></label>
                        <input id="attendanceIncentiveOvertimeHours" class="form-control" name="overtime_hours" type="number" min="0.01" max="99.99" step="0.01">
                    </div>
                    <div class="col-md-6 d-none" data-overtime-field>
                        <label class="form-label" for="attendanceIncentiveOvertimeHourlyRate">Valor de la hora extra <span class="text-danger">*</span></label>
                        <input id="attendanceIncentiveOvertimeHourlyRate" class="form-control" name="overtime_hourly_rate" type="number" min="0.01" max="99999.99" step="0.01">
                    </div>
                    <div class="col-md-7 d-none" data-incentive-details-field>
                        <label class="form-label" for="attendanceIncentiveDate">Fecha</label>
                        <input id="attendanceIncentiveDate" class="form-control" name="incentive_date" type="date" value="{{ $date }}" readonly>
                    </div>
                    <div class="col-12 d-none" data-incentive-details-field>
                        <label class="form-label" for="attendanceIncentiveNotes">Notas</label>
                        <textarea id="attendanceIncentiveNotes" class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar incentivo</button></div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
window.workerGroupAttendanceConfig = {
    markUrlBase: '{{ url('/human_resources/worker-groups/' . $workerGroup->id . '/attendance') }}',
    incentiveUrlBase: '{{ url('/human_resources/worker-groups/' . $workerGroup->id . '/attendance') }}',
    date: '{{ $date }}',
    csrfToken: '{{ csrf_token() }}',
};
</script>
<script src="{{ asset('assets/js/worker_group_attendance.js') }}"></script>
@endpush