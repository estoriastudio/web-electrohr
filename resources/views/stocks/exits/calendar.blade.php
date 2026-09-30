@extends('layouts.app')

@section('page_title', 'Vista Calendario de Retornos')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('stocks.exits.index') }}">Salidas de inventario</a></li>
    <li class="breadcrumb-item active">Calendario de retornos</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h4 class="card-title mb-0"><i class="ri-calendar-check-line me-1 text-primary"></i>Vista Calendario de Retornos</h4>
        <a href="{{ route('stocks.exits.index') }}" class="btn btn-sm btn-outline-primary"><i class="ri-list-check me-1"></i>Ver salidas</a>
    </div>
    <div class="card-body border-bottom py-3 d-flex flex-wrap align-items-center gap-3">
        <span class="badge bg-primary-subtle text-primary"><i class="ri-time-line me-1"></i>Pendiente de retorno</span>
        <span class="badge bg-danger-subtle text-danger"><i class="ri-alarm-warning-line me-1"></i>Retorno vencido</span>
        <span class="badge bg-success-subtle text-success"><i class="ri-check-line me-1"></i>Devuelta</span>
        <span id="return-calendar-loading" class="text-muted fs-13 ms-auto d-none" role="status"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Cargando retornos...</span>
    </div>
    <div class="card-body">
        <div id="return-calendar-error" class="alert alert-danger d-none" role="alert">
            No fue posible cargar los retornos.
            <button type="button" id="retry-return-calendar" class="btn btn-sm btn-outline-danger ms-2"><i class="ri-refresh-line me-1"></i>Reintentar</button>
        </div>
        <div id="return-calendar-empty" class="alert alert-light border d-none" role="status">No hay retornos programados en este periodo.</div>
        <div id="return-calendar"></div>
    </div>
</div>

<div class="modal fade" id="returnDetailsModal" tabindex="-1" aria-labelledby="returnDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="returnDetailsTitle">Préstamo de herramienta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <span id="return-status" class="badge mb-3"></span>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Vale</dt><dd class="col-sm-8 text-break" data-return-field="voucher"></dd>
                    <dt class="col-sm-4">Herramienta</dt><dd class="col-sm-8 text-break" data-return-field="tool"></dd>
                    <dt class="col-sm-4">Trabajador</dt><dd class="col-sm-8 text-break" data-return-field="worker"></dd>
                    <dt class="col-sm-4">Proyecto</dt><dd class="col-sm-8 text-break" data-return-field="project"></dd>
                    <dt class="col-sm-4">Obra</dt><dd class="col-sm-8 text-break" data-return-field="work"></dd>
                    <dt class="col-sm-4">Cantidad</dt><dd class="col-sm-8" data-return-field="quantity"></dd>
                    <dt class="col-sm-4">Fecha de salida</dt><dd class="col-sm-8" data-return-field="exitedAt"></dd>
                    <dt class="col-sm-4">Retorno previsto</dt><dd class="col-sm-8" data-return-field="expectedReturnAt"></dd>
                    <dt class="col-sm-4">Retorno registrado</dt><dd class="col-sm-8" data-return-field="returnedAt"></dd>
                    <dt class="col-sm-4">Observaciones</dt><dd class="col-sm-8 text-break" data-return-field="observations"></dd>
                </dl>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button></div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="{{ asset('assets/vendor/fullcalendar/main.min.css') }}" rel="stylesheet">
<style>
    #return-calendar .fc-event { cursor: pointer; }
    #return-calendar .fc-event-title { white-space: normal; overflow-wrap: anywhere; }
    #return-calendar .fc-toolbar { flex-wrap: wrap; gap: 12px; }
    #return-calendar .fc-toolbar-title { font-size: 18px; }
    @media (max-width: 575.98px) {
        #return-calendar .fc-toolbar-chunk { width: 100%; text-align: center; }
        #return-calendar .fc-button { padding: .35rem .5rem; }
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('assets/vendor/fullcalendar/main.min.js') }}"></script>
<script src="{{ asset('assets/vendor/fullcalendar/locales/es.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var errorAlert = document.getElementById('return-calendar-error');
    var emptyAlert = document.getElementById('return-calendar-empty');
    var loading = document.getElementById('return-calendar-loading');
    var details = document.getElementById('returnDetailsModal');
    var calendar = new FullCalendar.Calendar(document.getElementById('return-calendar'), {
        locale: 'es',
        initialView: window.innerWidth < 768 ? 'listMonth' : 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' },
        buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', list: 'Agenda' },
        height: 'auto',
        firstDay: 1,
        editable: false,
        selectable: false,
        dayMaxEvents: true,
        noEventsText: 'No hay retornos programados en este periodo.',
        events: @json(route('stocks.exits.calendar.events')),
        loading: function (isLoading) {
            loading.classList.toggle('d-none', !isLoading);
            if (isLoading) {
                errorAlert.classList.add('d-none');
                emptyAlert.classList.add('d-none');
            }
        },
        eventSourceSuccess: function (events) {
            emptyAlert.classList.toggle('d-none', events.length !== 0);
            return events;
        },
        eventSourceFailure: function () {
            errorAlert.classList.remove('d-none');
            emptyAlert.classList.add('d-none');
        },
        eventClick: function (info) {
            var data = info.event.extendedProps;
            details.querySelectorAll('[data-return-field]').forEach(function (element) {
                element.textContent = data[element.dataset.returnField] || 'Sin registro';
            });
            var status = document.getElementById('return-status');
            status.textContent = data.status;
            status.className = 'badge mb-3 ' + data.statusClass;
            bootstrap.Modal.getOrCreateInstance(details).show();
        },
        eventDidMount: function (info) {
            info.el.title = info.event.extendedProps.tool + ' | ' + info.event.extendedProps.worker + ' | ' + info.event.extendedProps.status;
        }
    });
    document.getElementById('retry-return-calendar').addEventListener('click', function () { calendar.refetchEvents(); });
    calendar.render();
});
</script>
@endpush