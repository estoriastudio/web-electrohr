@extends('layouts.app')

@section('page_title', 'Mis Vales de Material')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('supplier_portal.dashboard') }}">Portal</a></li>
    <li class="breadcrumb-item active">Vales de Material</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <h4 class="card-title mb-0">Mis vales de material</h4>
    </div>

    <div class="card-body border-bottom py-3">
        <form method="GET" action="{{ route('supplier_portal.material_vouchers.index') }}" class="d-flex gap-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Buscar por folio..." autocomplete="off">
                @if ($search)
                    <a href="{{ route('supplier_portal.material_vouchers.index') }}" class="btn btn-outline-secondary" title="Limpiar búsqueda">
                        <i class="ri-close-line"></i>
                    </a>
                @endif
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                <thead class="bg-light-subtle">
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Renglones</th>
                        <th>Estatus</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vouchers as $voucher)
                        <tr>
                            <td class="fw-semibold">{{ $voucher->folio }}</td>
                            <td>{{ $voucher->voucher_date?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $voucher->items_count }}</td>
                            <td>{{ ucfirst($voucher->status) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No hay vales de material registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($vouchers->hasPages())
        <div class="card-footer d-flex justify-content-end">
            {{ $vouchers->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
