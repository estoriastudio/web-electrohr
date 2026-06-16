@php
    $po       = $purchaseOrder;
    $supplier = $po->supplier;
    $ms       = $milestone;

    $logoPath = public_path('assets/images/logo-dark.png');
    $logoData = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : '';

    $condLabel = ($ms->payment_condition ?? 'credito') === 'contado' ? 'Contado' : 'Crédito';
    $currency  = $po->currency ?? 'MXN';

    function crNumToWords(int $n): string {
        if ($n === 0) return 'CERO';
        $u = ['','UN','DOS','TRES','CUATRO','CINCO','SEIS','SIETE','OCHO','NUEVE',
              'DIEZ','ONCE','DOCE','TRECE','CATORCE','QUINCE','DIECISÉIS','DIECISIETE',
              'DIECIOCHO','DIECINUEVE','VEINTE'];
        $d = ['','','VEINTI','TREINTA','CUARENTA','CINCUENTA','SESENTA','SETENTA','OCHENTA','NOVENTA'];
        $c = ['','CIENTO','DOSCIENTOS','TRESCIENTOS','CUATROCIENTOS','QUINIENTOS',
              'SEISCIENTOS','SETECIENTOS','OCHOCIENTOS','NOVECIENTOS'];
        if ($n <= 20) return $u[$n];
        if ($n === 100) return 'CIEN';
        if ($n < 100) { $dz=intdiv($n,10); $un=$n%10; return $un===0?$d[$dz]:($dz===2?'VEINTI'.strtolower($u[$un]):$d[$dz].' Y '.$u[$un]); }
        if ($n < 1000) { $cv=intdiv($n,100); $r=$n%100; return $r===0?$c[$cv]:$c[$cv].' '.crNumToWords($r); }
        if ($n < 2000) return 'MIL'.($n%1000>0?' '.crNumToWords($n%1000):'');
        if ($n < 1000000) { $m=intdiv($n,1000); $r=$n%1000; return crNumToWords($m).' MIL'.($r>0?' '.crNumToWords($r):''); }
        if ($n < 2000000) return 'UN MILLÓN'.($n%1000000>0?' '.crNumToWords($n%1000000):'');
        $m=intdiv($n,1000000); $r=$n%1000000;
        return crNumToWords($m).' MILLONES'.($r>0?' '.crNumToWords($r):'');
    }

    $amt      = (float) $payment->amount;
    $enteros  = (int) floor($amt);
    $decs     = str_pad((int) round(($amt - $enteros) * 100), 2, '0', STR_PAD_LEFT);
    $letras   = crNumToWords($enteros) . ' ' . $currency . ' CON ' . $decs . '/100';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrarecibo {{ $payment->folio }}</title>
    <style>
        @page { size: letter portrait; margin: 15mm; }
        body { margin: 0; padding: 0; }
        .header-table { border-collapse: collapse; width: 100%; }
        .header-table td { border: 1px solid #999; padding: 8px; }
        .section-title {
            background-color: #003366;
            color: #ffffff;
            font-weight: bold;
            font-size: 11px;
            padding: 5px 8px;
        }
        .data-table { border-collapse: collapse; width: 100%; }
        .data-table td, .data-table th { border: 1px solid #bbb; padding: 5px 8px; font-size: 10px; }
        .data-table th { background-color: #e8f0fb; font-weight: bold; text-align: left; }
        .amount-box {
            border: 2px solid #003366;
            padding: 8px 12px;
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            color: #003366;
        }
        .footer-sign { width: 45%; border-top: 1px solid #000; padding-top: 4px; text-align: center; font-size: 10px; }
    </style>
</head>
<body style="font-family: Arial, sans-serif; font-size: 11px; color: #1a1a1a;">

<!-- ENCABEZADO -->
<table class="header-table">
    <tr>
        <!-- Logo -->
        <td width="35%" style="vertical-align: middle;">
            @if ($logoData)
            <img src="{{ $logoData }}" alt="ELECTRO SERVICIOS" style="max-width: 170px; max-height: 65px;"><br>
            @endif
            <span style="font-size: 9px; color: #555;">ELECTRO SERVICIOS HR, S.A. DE C.V.</span>
        </td>
        <!-- Título + folio -->
        <td width="65%" style="vertical-align: top;">
            <table width="100%" cellpadding="2" cellspacing="0" border="0">
                <tr>
                    <td style="font-size: 15px; font-weight: bold; color: #003366; text-align: right; padding-bottom: 4px;">
                        CONTRARECIBO
                    </td>
                </tr>
                <tr>
                    <td style="text-align: right; font-size: 12px; color: #444;">
                        Folio: <strong>{{ $payment->folio }}</strong>
                    </td>
                </tr>
                <tr>
                    <td style="text-align: right; font-size: 10px; color: #666; padding-top: 4px;">
                        Fecha de emisión: {{ \Carbon\Carbon::now()->format('d/m/Y') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<br>

<!-- DATOS DEL PROVEEDOR -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 4px;">
    <tr><td class="section-title">DATOS DEL PROVEEDOR</td></tr>
</table>
<table class="data-table" style="margin-bottom: 12px;">
    <tr>
        <th width="30%">Razón Social</th>
        <td>{{ $supplier->rfc_name ?? '—' }}</td>
        <th width="20%">RFC</th>
        <td>{{ $supplier->rfc ?? '—' }}</td>
    </tr>
    <tr>
        <th>Nombre Comercial</th>
        <td colspan="3">{{ $supplier->commercial_name ?? '—' }}</td>
    </tr>
</table>

<!-- DATOS DE LA ORDEN DE COMPRA -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 4px;">
    <tr><td class="section-title">ORDEN DE COMPRA</td></tr>
</table>
<table class="data-table" style="margin-bottom: 12px;">
    <tr>
        <th width="25%">Número OC</th>
        <td>{{ $po->folio ?? 'OC #' . $po->id }}</td>
        <th width="20%">Moneda</th>
        <td>{{ $currency }}</td>
    </tr>
    <tr>
        <th>Proyecto</th>
        <td>{{ $po->projectRelation->name ?? $po->project ?? '—' }}</td>
        <th>Obra</th>
        <td>{{ $po->workRelation->name ?? $po->site ?? '—' }}</td>
    </tr>
    <tr>
        <th>Condición de hito</th>
        <td>{{ $condLabel }}</td>
        <th>Hito #</th>
        <td>{{ $ms->id }}</td>
    </tr>
</table>

<!-- DATOS DEL PAGO -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 4px;">
    <tr><td class="section-title">DETALLE DEL PAGO</td></tr>
</table>
<table class="data-table" style="margin-bottom: 12px;">
    <tr>
        <th width="30%">Folio de pago</th>
        <td>{{ $payment->folio }}</td>
        <th width="25%">Referencia</th>
        <td>{{ $payment->reference_number ?? '—' }}</td>
    </tr>
    <tr>
        <th>Fecha de factura</th>
        <td>{{ $payment->invoice_date ? $payment->invoice_date->format('d/m/Y') : '—' }}</td>
        <th>Fecha de pago</th>
        <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <th>Estatus</th>
        <td>{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</td>
        <th>Importe</th>
        <td><strong>{{ $currency }} {{ number_format($payment->amount, 2) }}</strong></td>
    </tr>
</table>

<!-- IMPORTE EN LETRA -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 16px;">
    <tr>
        <td class="amount-box">
            SON: {{ $letras }}
        </td>
    </tr>
</table>

<!-- LEYENDA -->
<p style="font-size: 9px; color: #666; border: 1px solid #ddd; padding: 6px; margin-bottom: 24px;">
    Este documento acredita la recepción de la factura del proveedor y el compromiso de pago por parte de
    <strong>ELECTRO SERVICIOS HR, S.A. DE C.V.</strong> conforme a las condiciones de la Orden de Compra indicada.
    No es un comprobante fiscal. Fecha de vencimiento: {{ $ms->due_date ? $ms->due_date->format('d/m/Y') : '—' }}.
</p>

<!-- FIRMAS -->
<table width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td width="10%"></td>
        <td class="footer-sign" width="35%">
            <br><br>
            <strong>Elaborado por / Compras</strong><br>
            ELECTRO SERVICIOS HR, S.A. DE C.V.
        </td>
        <td width="10%"></td>
        <td class="footer-sign" width="35%">
            <br><br>
            <strong>Recibido por / Proveedor</strong><br>
            {{ $supplier->rfc_name ?? '—' }}
        </td>
        <td width="10%"></td>
    </tr>
</table>

</body>
</html>
