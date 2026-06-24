@php
    $po         = $purchaseOrder;
    $items      = $po->items;
    $milestones = $po->milestones->sortBy('id')->values();

    $logoPath = public_path('assets/images/logo-dark.png');
    $logoData = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : '';

    $fechaOC  = $po->created_at ? $po->created_at->format('d/m/Y') : \Carbon\Carbon::now()->format('d/m/Y');
    $currency = $po->currency ?? 'MXN';

    $lugarEntrega = $po->purchaseRequest?->delivery_address
        ?? $po->workRelation?->address
        ?? $po->projectRelation?->address
        ?? $po->site
        ?? '—';
    $proyecto     = $po->projectRelation?->name ?? $po->project ?? '—';
    $obra         = $po->workRelation?->name ?? $po->site ?? '—';

    function numToWordsPDF(int $n): string {
        if ($n === 0) return 'CERO';
        $u = ['','UN','DOS','TRES','CUATRO','CINCO','SEIS','SIETE','OCHO','NUEVE',
              'DIEZ','ONCE','DOCE','TRECE','CATORCE','QUINCE','DIECISÉIS','DIECISIETE',
              'DIECIOCHO','DIECINUEVE','VEINTE'];
        $d = ['','','VEINTI','TREINTA','CUARENTA','CINCUENTA','SESENTA','SETENTA','OCHENTA','NOVENTA'];
        $c = ['','CIENTO','DOSCIENTOS','TRESCIENTOS','CUATROCIENTOS','QUINIENTOS',
              'SEISCIENTOS','SETECIENTOS','OCHOCIENTOS','NOVECIENTOS'];
        if ($n <= 20) return $u[$n];
        if ($n === 100) return 'CIEN';
        if ($n < 100)  { $dz=intdiv($n,10); $un=$n%10; return $un===0?$d[$dz]:($dz===2?'VEINTI'.strtolower($u[$un]):$d[$dz].' Y '.$u[$un]); }
        if ($n < 1000) { $cv=intdiv($n,100); $r=$n%100; return $r===0?$c[$cv]:$c[$cv].' '.numToWordsPDF($r); }
        if ($n < 2000) return 'MIL'.($n%1000>0?' '.numToWordsPDF($n%1000):'');
        if ($n < 1000000) { $m=intdiv($n,1000); $r=$n%1000; return numToWordsPDF($m).' MIL'.($r>0?' '.numToWordsPDF($r):''); }
        if ($n < 2000000) return 'UN MILLÓN'.($n%1000000>0?' '.numToWordsPDF($n%1000000):'');
        $m=intdiv($n,1000000); $r=$n%1000000;
        return numToWordsPDF($m).' MILLONES'.($r>0?' '.numToWordsPDF($r):'');
    }
    $totalAmt  = $po->total_with_iva;
    $enteros   = (int) floor($totalAmt);
    $decimales = str_pad((int) round(($totalAmt - $enteros) * 100), 2, '0', STR_PAD_LEFT);
    $letras    = numToWordsPDF($enteros) . ' ' . $currency . ' CON ' . $decimales . '/100';

    $solmatRequester = $po->purchaseRequest?->materialRequest?->requestedBy?->name
        ?? $po->purchaseRequest?->materialRequest?->requested_by
        ?? $po->elaborated_by
        ?? '—';

    $tipoHitoMap = ['anticipo' => 'Anticipo', 'regular' => 'Pago Regular'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra</title>
    <style>
        @page { size: letter portrait; margin: 15mm; }
        body { margin: 0; padding: 0; }
        table { page-break-inside: auto; }
        tr { page-break-inside: auto; page-break-after: auto; }
        .section { width: 100%; border-collapse: collapse; border-color: #999; }
        .section-joined { margin-top: -1px; }
        .concept-table { font-size: 10px; line-height: 1.25; }
        .concept-table thead { display: table-header-group; }
        .concept-table tfoot { display: table-row-group; }
        .concept-table tbody tr { page-break-inside: avoid; }
        .concept-table td, .concept-table th { word-break: break-word; }
        .avoid-break { page-break-inside: avoid; }
        .terms-signatures {
            page-break-inside: avoid;
            break-inside: avoid-page;
        }
    </style>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 11px; color: #000;">

<!-- FILA 1: Logo + Título OC + Datos de facturación -->
<table width="100%" cellpadding="4" cellspacing="0" border="1" class="section">
    <tr>
        <!-- Logo -->
        <td width="35%" style="padding: 10px; vertical-align: middle; border: 1px solid #999;">
            @if ($logoData)
            <img src="{{ $logoData }}" alt="ELECTRO SERVICIOS" style="max-width: 180px; max-height: 70px;" /><br>
            @endif
            <span style="font-size: 10px; color: #555;">ELECTRO SERVICIOS HR, S.A. DE C.V.</span>
        </td>
        <!-- Datos de facturación -->
        <td width="65%" style="padding: 10px; vertical-align: top; border: 1px solid #999;">
            <table width="100%" cellpadding="2" cellspacing="0" border="0">
                <tr>
                    <td style="text-align: right; font-size: 14px; font-weight: bold; padding-bottom: 6px;">
                        Orden de Compra: #{{ str_pad($po->folio, 5, '0', STR_PAD_LEFT) }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px 0;">
                        <strong>Facturar A:</strong> Electro Servicios HR, S.A. de C.V.
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px 0;">
                        <strong>RFC:</strong> ESH1011011E4
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px 0;">
                        <strong>Domicilio:</strong> KUKULKAN KM 9, Piso 1 - Of. 2, Zona Hotelera, Cancún, Q. Roo C.P. 77500
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px 0;">
                        <strong>Tel:</strong> 01(472)72 2 29 82
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px 0;">
                        facturas@electrohr.com
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- FILA 2: Requisición + Fecha -->
<table width="100%" cellpadding="4" cellspacing="0" border="1" class="section section-joined avoid-break">
    <tr>
        <td width="50%" style="padding: 5px 8px; border: 1px solid #999;">
            <strong>Requisición N°:</strong> {{ $po->purchaseRequest?->folio ? '#'.$po->purchaseRequest->folio : '—' }}
        </td>
        <td width="50%" style="padding: 5px 8px; border: 1px solid #999;">
            <strong>Fecha Orden Compra:</strong> {{ $fechaOC }}
        </td>
    </tr>
</table>

<!-- FILA 3: Lugar de entrega + Solicita -->
<table width="100%" cellpadding="4" cellspacing="0" border="1" class="section section-joined avoid-break">
    <tr>
        <!-- Columna izquierda: lugar de entrega + proyecto + factura + obra -->
        <td width="55%" style="padding: 8px; vertical-align: top; border: 1px solid #999;">
            <strong>Lugar de entrega o ejecución:</strong><br>
            {{ $lugarEntrega }}<br><br>
            <strong>Proyecto:</strong> {{ $proyecto }}<br><br>
            <strong>Lugar de entrega de factura (Original y 3 ejemplares)</strong><br>
            KUKULKAN KM 9, Piso 1 - Of. 2, Zona Hotelera, Cancún, Q. Roo<br><br>
            <strong>Obra:</strong> {{ $obra }}
        </td>
        <!-- Columna derecha: solicitante + contacto -->
        <td width="45%" style="padding: 8px; vertical-align: top; border: 1px solid #999;">
            <strong>Solicita:</strong> {{ $solmatRequester }}<br><br>
            <strong>{{ $po->supplier->rfc_name ?? $po->supplier->commercial_name ?? '—' }}</strong><br>
            {{ $po->supplier->address ?? '' }}<br>
            @if($po->supplier->phone ?? $po->supplier->cellphone ?? '')TEL. {{ $po->supplier->phone ?? $po->supplier->cellphone }}<br>@endif
            RFC: {{ $po->supplier->rfc_num ?? '—' }}<br><br>
            <strong>Contacto:</strong> {{ $po->supplier->attended_by ?? $po->supplier->email ?? '—' }}
        </td>
    </tr>
</table>

<!-- FILA 4: Tabla de conceptos -->
<table width="100%" cellpadding="5" cellspacing="0" border="1" class="section section-joined concept-table">
    <thead>
        <tr style="background-color: #f0f0f0;">
            <th style="border: 1px solid #999; padding: 5px; text-align: center; width: 5%;">No.</th>
            <th style="border: 1px solid #999; padding: 5px; text-align: center; width: 35%;">Concepto</th>
            <th style="border: 1px solid #999; padding: 5px; text-align: center; width: 8%;">Cant.</th>
            <th style="border: 1px solid #999; padding: 5px; text-align: center; width: 12%;">Unidad</th>
            <th style="border: 1px solid #999; padding: 5px; text-align: center; width: 15%;">P/U</th>
            <th style="border: 1px solid #999; padding: 5px; text-align: center; width: 15%;">Imp. Total</th>
            <th style="border: 1px solid #999; padding: 5px; text-align: center; width: 10%;">Fecha Entrega</th>
        </tr>
    </thead>
    <tbody>
        @forelse($po->items as $i => $item)
        <tr>
            <td style="border: 1px solid #999; padding: 5px; text-align: center;">{{ $i + 1 }}</td>
            <td style="border: 1px solid #999; padding: 5px;">{{ $item->description }}</td>
            <td style="border: 1px solid #999; padding: 5px; text-align: center;">{{ number_format($item->quantity, 2) }}</td>
            <td style="border: 1px solid #999; padding: 5px; text-align: center;">{{ $item->unit }}</td>
            <td style="border: 1px solid #999; padding: 5px; text-align: right;">$ {{ number_format($item->unit_price, 2) }}</td>
            <td style="border: 1px solid #999; padding: 5px; text-align: right;">$ {{ number_format($item->total, 2) }}</td>
            <td style="border: 1px solid #999; padding: 5px; text-align: center;">{{ $item->delivery_date ?? '—' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" style="border: 1px solid #999; padding: 8px; text-align: center; color: #999;">Sin conceptos registrados.</td>
        </tr>
        @endforelse
    </tbody>
</table>

<!-- FILA 5: Totales (alineados a la derecha) -->
<div class="avoid-break" style="margin-top: -1px;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <!-- Espacio izquierdo vacío -->
            <td width="55%">&nbsp;</td>
            <!-- Tabla de totales -->
            <td width="45%" style="padding: 0;">
                <table width="100%" cellpadding="5" cellspacing="0" border="1" class="section">
                    <tr>
                        <td style="border: 1px solid #999; padding: 5px; font-weight: bold; background-color: #f0f0f0;">Subtotal</td>
                        <td style="border: 1px solid #999; padding: 5px; text-align: right; font-weight: bold;">$ {{ number_format($po->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #999; padding: 5px; font-weight: bold; background-color: #f0f0f0;">IVA</td>
                        <td style="border: 1px solid #999; padding: 5px; text-align: right;">$ {{ number_format($po->iva, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #999; padding: 5px; font-weight: bold; background-color: #f0f0f0;">Total</td>
                        <td style="border: 1px solid #999; padding: 5px; text-align: right; font-weight: bold;">$ {{ number_format($po->total_with_iva, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<div class="terms-signatures">
    <!-- FILA 6: Moneda, importe con letra, condiciones de pago, observaciones -->
    <table width="100%" cellpadding="6" cellspacing="0" border="1" class="section" style="margin-top: 10px;">
        <tr>
            <td style="border: 1px solid #999; padding: 8px; line-height: 1.6;">
                <strong>Moneda:</strong> {{ $currency }}<br>
                <strong>Importe con Letra:</strong> {{ $letras }}<br>
                <strong>Condiciones de Pago:</strong>
                @if($milestones->count() > 0)
                    @foreach($milestones as $m)
                        H{{ $loop->iteration }}: {{ $m->concept ?: ($tipoHitoMap[$m->type] ?? $m->type) }} — {{ $m->value_type === 'porcentaje' ? $m->value.'% ($ '.number_format($m->effective_amount,2).')' : '$ '.number_format($m->value,2) }}{{ $m->due_date ? ' — Vence: '.$m->due_date->format('d/m/Y') : '' }}<br>
                    @endforeach
                @else
                    —
                @endif
                <br><br>
                <strong>Observaciones:</strong>

                @if($po->observations && count($po->observations) > 0)
                    @foreach($po->observations as $note){{ $note['user_name'] ?? '' }}: {{ $note['text'] ?? '' }}
                        @if(!$loop->last) | @endif
                    @endforeach
                @else
                    Sin observaciones.
                @endif
            </td>
        </tr>
    </table>

    <!-- ESPACIO -->
    <div style="height: 20px;"></div>

    <!-- FILA 7: Firmas -->
    <table width="100%" cellpadding="10" cellspacing="0" border="1" class="section">
        <tr>
            <!-- Firma 1 -->
            <td width="33%" style="border: 1px solid #999; padding: 10px; text-align: center; vertical-align: bottom;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="text-align: center; padding-bottom: 5px; font-size: 10px; color: #555;">
                            Elabora Orden
                        </td>
                    </tr>
                    <!-- Área de firma (espacio en blanco o imagen) -->
                    <tr>
                        <td style="height: 50px; text-align: center; vertical-align: bottom;">
                            <!-- Firma digital o espacio vacío -->
                            &nbsp;
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top: 1px solid #000; padding-top: 5px; text-align: center; font-size: 10px;">
                            <strong>{{ $po->elaborated_by ?? '___________________' }}</strong>
                        </td>
                    </tr>
                </table>
            </td>
            <!-- Firma 2 -->
            <td width="34%" style="border: 1px solid #999; padding: 10px; text-align: center; vertical-align: bottom;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="text-align: center; padding-bottom: 5px; font-size: 10px; color: #555;">
                            Aceptación del Proveedor
                        </td>
                    </tr>
                    <tr>
                        <td style="height: 50px; text-align: center; vertical-align: bottom;">
                            &nbsp;
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top: 1px solid #000; padding-top: 5px; text-align: center; font-size: 10px;">
                            <strong>{{ $po->supplier_signatory ?? '___________________' }}</strong>
                        </td>
                    </tr>
                </table>
            </td>
            <!-- Firma 3 -->
            <td width="33%" style="border: 1px solid #999; padding: 10px; text-align: center; vertical-align: bottom;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="text-align: center; padding-bottom: 5px; font-size: 10px; color: #555;">
                            Autorización de Pedido
                        </td>
                    </tr>
                    <tr>
                        <td style="height: 50px; text-align: center; vertical-align: bottom;">
                            &nbsp;
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top: 1px solid #000; padding-top: 5px; text-align: center; font-size: 10px;">
                            <strong>{{ $po->authorized_signatory ?? '___________________' }}</strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
</body>
</html>