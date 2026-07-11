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

    $lugarEntrega = $po->site
        ?? $po->purchaseRequest?->delivery_address
        ?? $po->workRelation?->address
        ?? $po->projectRelation?->address
        ?? '—';
    $proyecto     = $po->projectRelation?->name ?? $po->project ?? '—';
    $obra         = $po->workRelation?->name ?? $po->site ?? '—';

    if (!function_exists('numToWordsPDF')) {
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

@include('purchase_orders.partials._pdf_body')
{{-- original body content is now in the partial above --}}


</body>
</html>
