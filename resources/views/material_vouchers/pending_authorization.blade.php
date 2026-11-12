@extends('layouts.app')

@section('page_title', 'Vales por autorizar')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Vales por autorizar</li>
@endsection

@section('content')

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <div>
            <h4 class="card-title mb-0"><i class="ri-shield-check-line me-1"></i> Vales por autorizar</h4>
            <p class="text-muted fs-13 mb-0">Vales en estatus Emitido. Ábrelos para revisarlos y autorizarlos con firma.</p>
        </div>
        <span class="text-muted fs-13">{{ $materialVouchers->total() }} vales</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
                <thead class="bg-light-subtle">
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th>Proyecto / Obra</th>
                        <th>Solicitante</th>
                        <th class="text-center">Renglones</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($materialVouchers as $voucher)
                        <tr>
                            <td class="fw-semibold">{{ $voucher->folio }}</td>
                            <td>{{ $voucher->voucher_date?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $voucher->supplier?->commercial_name ?: $voucher->supplier?->rfc_name ?: '—' }}</td>
                            <td>
                                {{ $voucher->project?->name ?? '—' }}
                                @if ($voucher->projectWork)
                                    <div class="text-muted fs-12">{{ $voucher->projectWork->name }}</div>
                                @endif
                            </td>
                            <td>{{ $voucher->requester ?: $voucher->createdBy?->name ?: '—' }}</td>
                            <td class="text-center">{{ $voucher->items_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('material_vouchers.show', $voucher) }}" class="btn btn-sm btn-info">
                                    <i class="ri-quill-pen-line me-1"></i> Revisar y autorizar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="ri-checkbox-circle-line fs-24 d-block mb-1 opacity-50"></i>
                                No hay vales por autorizar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($materialVouchers->hasPages())
        <div class="card-footer d-flex justify-content-end">{{ $materialVouchers->links() }}</div>
    @endif
</div>

@endsection
