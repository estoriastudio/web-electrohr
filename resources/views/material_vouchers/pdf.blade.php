@php
    $voucher = $materialVoucher;
    $logoPath = public_path('assets/images/logo-dark.png');
    $logoData = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : '';
    $supplierName = $voucher->supplier?->commercial_name ?: $voucher->supplier?->rfc_name;
    $items = $voucher->items ?? collect();
    $notes = is_array($voucher->observations) ? $voucher->observations : [];
    $obsText = implode(' | ', array_map(fn($n) => $n['text'] ?? '', $notes));
    $signature = $voucher->authorized_signature_name ?: ($voucher->authorizedBy?->name ?? '');
    $signatureImage = $voucher->authorized_signature ?? '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vale {{ $voucher->folio }}</title>
    <style>
        @page { size: 396pt 612pt; margin: 8mm; }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #111;
            margin: 0;
            padding: 0;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 0.4px;
        }
        .line {
            border-bottom: 1px solid #111;
            min-height: 15px;
            padding: 2px 4px;
        }
        .small-line {
            border-bottom: 1px solid #111;
            min-height: 14px;
            padding: 1px 4px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .table th, .table td {
            border: 1px solid #111;
            padding: 3px;
            vertical-align: top;
        }
        .table th {
            text-align: left;
            font-size: 10px;
        }
        .muted { color: #374151; }
        .folio-red { color: #a43232; font-weight: bold; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { border: none; vertical-align: top; }
    </style>
</head>
<body>
    <table class="header-table" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
        <tr>
            <td width="40%" style="vertical-align: middle; text-align: left;">
                @if($logoData)
                    <img src="{{ $logoData }}" alt="ELECTRO SERVICIOS" style="max-width: 160px; max-height: 52px;" />
                @endif
            </td>
            <td width="60%" style="padding-left: 8px;">
                <div class="title" style="line-height: 1.05;">Vale de Material</div>
                <div style="margin-top: 4px; font-size: 10.5px; line-height: 1.2;">
                    Calle Topacio No.1 | Fracc. Esmeralda | Silao, Gto.<br>
                    Tel. Ofic: 472 7222982 | 472 7224385
                </div>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:5px;">
        <tr>
            <td width="48%" style="padding-right: 8px;">
                <div class="muted" style="font-size:10px; margin-bottom: 2px;">FECHA</div>
                <div class="small-line">{{ $voucher->voucher_date?->format('d/m/Y') }}</div>
            </td>
            <td width="4%"></td>
            <td width="48%">
                <div class="muted" style="font-size:10px; margin-bottom: 2px;">FOLIO</div>
                <div class="small-line"><span class="folio-red">{{ $voucher->folio }}</span></div>
            </td>
        </tr>
    </table>

    <div style="margin-top:10px;">
        <div class="muted" style="font-size:10px;">VALE A FAVOR DE:</div>
        <div class="line">{{ $supplierName }}</div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th width="14%">CANT.</th>
                <th width="16%">UNIDAD</th>
                <th width="70%">DESCRIPCIÓN</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ number_format((float) $item->quantity, 2) }}</td>
                    <td>{{ $item->unit }}</td>
                    <td>{{ $item->description }}</td>
                </tr>
            @endforeach
            @for($i = $items->count(); $i < 8; $i++)
                <tr>
                    <td style="height:14px;">&nbsp;</td>
                    <td></td>
                    <td></td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div style="margin-top:10px;">
        <div class="muted" style="font-size:10px;">OBSERVACIONES:</div>
        <div class="line" style="min-height:24px;">{{ $obsText }}</div>
    </div>

    <div style="margin-top:12px;">
        <div class="muted" style="font-size:10px;">AUTORIZÓ</div>
        @if($signatureImage)
            <div style="margin-top:4px; width: 200px; min-height: 46px;">
                <img src="{{ $signatureImage }}" alt="Firma" style="max-height: 46px; width: auto; max-width: 100%;">
            </div>
        @endif
        <div style="margin-top: 4px; font-weight: bold;">{{ $signature }}</div>
    </div>
</body>
</html>
