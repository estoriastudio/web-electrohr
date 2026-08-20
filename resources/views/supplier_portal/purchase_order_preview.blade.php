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

    if (!function_exists('numToWordsPreview')) {
        function numToWordsPreview(int $n): string {
            if ($n === 0) return 'CERO';
            $u = ['','UN','DOS','TRES','CUATRO','CINCO','SEIS','SIETE','OCHO','NUEVE',
                  'DIEZ','ONCE','DOCE','TRECE','CATORCE','QUINCE','DIECISEIS','DIECISIETE',
                  'DIECIOCHO','DIECINUEVE','VEINTE'];
            $d = ['','','VEINTI','TREINTA','CUARENTA','CINCUENTA','SESENTA','SETENTA','OCHENTA','NOVENTA'];
            $c = ['','CIENTO','DOSCIENTOS','TRESCIENTOS','CUATROCIENTOS','QUINIENTOS',
                  'SEISCIENTOS','SETECIENTOS','OCHOCIENTOS','NOVECIENTOS'];
            if ($n <= 20) return $u[$n];
            if ($n === 100) return 'CIEN';
            if ($n < 100) {
                $dz = intdiv($n, 10);
                $un = $n % 10;
                return $un === 0 ? $d[$dz] : ($dz === 2 ? 'VEINTI' . strtolower($u[$un]) : $d[$dz] . ' Y ' . $u[$un]);
            }
            if ($n < 1000) {
                $cv = intdiv($n, 100);
                $r = $n % 100;
                return $r === 0 ? $c[$cv] : $c[$cv] . ' ' . numToWordsPreview($r);
            }
            if ($n < 2000) return 'MIL' . ($n % 1000 > 0 ? ' ' . numToWordsPreview($n % 1000) : '');
            if ($n < 1000000) {
                $m = intdiv($n, 1000);
                $r = $n % 1000;
                return numToWordsPreview($m) . ' MIL' . ($r > 0 ? ' ' . numToWordsPreview($r) : '');
            }
            if ($n < 2000000) return 'UN MILLON' . ($n % 1000000 > 0 ? ' ' . numToWordsPreview($n % 1000000) : '');
            $m = intdiv($n, 1000000);
            $r = $n % 1000000;
            return numToWordsPreview($m) . ' MILLONES' . ($r > 0 ? ' ' . numToWordsPreview($r) : '');
        }
    }
    $totalAmt  = $po->total_with_iva;
    $enteros   = (int) floor($totalAmt);
    $decimales = str_pad((int) round(($totalAmt - $enteros) * 100), 2, '0', STR_PAD_LEFT);
    $letras    = numToWordsPreview($enteros) . ' ' . $currency . ' CON ' . $decimales . '/100';

    $solmatRequester = $po->purchaseRequest?->materialRequest?->requestedBy?->name
        ?? $po->purchaseRequest?->materialRequest?->requested_by
        ?? $po->elaborated_by
        ?? '—';

    $supplierContact = $po->supplier?->contacts->firstWhere('is_primary', true)
        ?? $po->supplier?->contacts->first();
    $supplierAddress = implode(', ', array_filter([
        $po->supplier?->street,
        $po->supplier?->colony,
        $po->supplier?->postal_code ? 'C.P. ' . $po->supplier->postal_code : null,
        $po->supplier?->city,
        $po->supplier?->state,
    ]));

    $sourceSolmats = $po->purchaseRequest?->materialRequests ?? collect();
    if ($sourceSolmats->isEmpty() && $po->purchaseRequest?->materialRequest) {
        $sourceSolmats = collect([$po->purchaseRequest->materialRequest]);
    }
    $sourceSolmatFolios = $sourceSolmats
        ->pluck('folio')
        ->filter(fn ($folio) => $folio !== null && $folio !== '')
        ->sort()
        ->values();

    $tipoHitoMap = ['anticipo' => 'Anticipo', 'regular' => 'Pago Regular'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista previa OC</title>
    <style>
        html, body { margin: 0; padding: 0; background: #eef1f5; }
        body { font-family: Arial, sans-serif; color: #000; }
        .preview-wrap {
            max-width: 960px;
            margin: 1rem auto;
            padding: 1rem;
            background: #fff;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
        }
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
<body>
    <div class="preview-wrap">
        @include('purchase_orders.partials._pdf_body')
    </div>
</body>
</html>
