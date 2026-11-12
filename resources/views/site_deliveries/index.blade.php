@extends('layouts.app')

@section('page_title', 'Entregas en obra')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Entregas en obra</li>
@endsection

@section('content')

@php
    $sections = [
        'pending' => ['label' => 'Por confirmar', 'class' => 'warning'],
        'confirmed' => ['label' => 'Recibidas', 'class' => 'success'],
        'discrepancy' => ['label' => 'Con diferencias', 'class' => 'danger'],
    ];
@endphp

<div class="card">
    <div class="card-header border-bottom">
        <h4 class="card-title mb-1"><i class="ri-truck-line me-1"></i> Entregas de Almacén a obra</h4>
        <p class="text-muted fs-13 mb-0">Salidas definitivas de Almacén hacia una obra. El personal de la obra confirma lo que recibió contra lo que se envió.</p>
    </div>
    <div class="card-body border-bottom py-2">
        <ul class="nav nav-pills gap-2">
            @foreach ($sections as $key => $meta)
                <li class="nav-item">
                    <a class="nav-link py-1 px-3 {{ $section === $key ? 'active' : '' }}" href="{{ route('site_deliveries.index', ['section' => $key]) }}">
                        {{ $meta['label'] }} <span class="badge bg-{{ $meta['class'] }}-subtle text-{{ $meta['class'] }} ms-1">{{ $counts[$key] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
    <div class="card-body">
        @forelse ($exits as $exit)
            @php
                $confirmation = $exit->siteDeliveryConfirmation;
                $lines = $exit->sentLines()->values();
                $canRegister = ! $confirmation && (auth()->user()->hasRole('admin')
                    || (auth()->user()->can('site_delivery_confirmations.register') && in_array((int) $exit->project_work_id, auth()->user()->assignedProjectWorkIds(), true)));
            @endphp
            <div class="border rounded p-3 mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <div>
                        <span class="fw-semibold">Salida #{{ $exit->id }}</span>
                        @if ($exit->voucher_number)<span class="text-muted fs-13">· Vale {{ $exit->voucher_number }}</span>@endif
                        <div class="fs-13 text-muted">
                            {{ $exit->project?->name }} · {{ $exit->projectWork?->name }} · enviada el {{ $exit->exited_at?->format('d/m/Y') }}
                            @if ($exit->recipient_name) · recibe {{ $exit->recipient_name }} @endif
                        </div>
                    </div>
                    @if ($confirmation)
                        <span class="align-self-start badge bg-{{ $confirmation->hasDiscrepancy() ? 'danger' : 'success' }}-subtle text-{{ $confirmation->hasDiscrepancy() ? 'danger' : 'success' }}">
                            {{ $confirmation->hasDiscrepancy() ? 'Con diferencias' : 'Recibida' }} · {{ $confirmation->confirmedBy?->name }} · {{ $confirmation->confirmed_at->format('d/m/Y') }}
                        </span>
                    @endif
                </div>

                @if ($canRegister)
                    <form action="{{ route('site_deliveries.store', $exit) }}" method="POST">
                        @csrf
                @endif
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <thead class="bg-light-subtle"><tr><th>Código</th><th>Descripción</th><th class="text-end">Enviado</th><th class="text-end" style="width: 160px;">Recibido</th></tr></thead>
                        <tbody>
                            @foreach ($lines as $index => $line)
                                @php($receivedItem = $confirmation?->items->get($index))
                                <tr>
                                    <td>{{ $line['concept']?->code ?? '—' }}</td>
                                    <td>{{ $line['concept']?->description ?? '—' }}</td>
                                    <td class="text-end">{{ rtrim(rtrim(number_format($line['quantity'], 3, '.', ''), '0'), '.') }} {{ $line['concept']?->unit }}</td>
                                    <td class="text-end">
                                        @if ($canRegister)
                                            <input type="number" step="0.001" min="0" name="received[{{ $index }}]" value="{{ old('received.'.$index, $line['quantity']) }}" class="form-control form-control-sm text-end" required>
                                        @elseif ($receivedItem)
                                            <span class="{{ (float) $receivedItem->received_quantity !== (float) $receivedItem->sent_quantity ? 'text-danger fw-semibold' : '' }}">
                                                {{ rtrim(rtrim(number_format((float) $receivedItem->received_quantity, 3, '.', ''), '0'), '.') }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($canRegister)
                        <div class="row g-2 align-items-end">
                            <div class="col-md-9">
                                <input type="text" name="notes" class="form-control form-control-sm @error('notes') is-invalid @enderror" placeholder="Observaciones (obligatorias si hay diferencias)" maxlength="2000" value="{{ old('notes') }}">
                                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3 d-grid">
                                <button type="submit" class="btn btn-sm btn-success"><i class="ri-check-double-line me-1"></i>Confirmar recepción</button>
                            </div>
                        </div>
                    </form>
                @elseif ($confirmation?->notes)
                    <div class="fs-13 text-muted"><i class="ri-chat-3-line me-1"></i>{{ $confirmation->notes }}</div>
                @endif
            </div>
        @empty
            <div class="text-center text-muted py-5">
                <i class="ri-truck-line fs-24 d-block mb-1 opacity-50"></i>
                No hay entregas en esta sección.
            </div>
        @endforelse

        @if ($exits->hasPages())
            <div class="d-flex justify-content-end">{{ $exits->links() }}</div>
        @endif
    </div>
</div>

@endsection
