<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato — Condiciones Particulares del Pedido</title>
    <style>
        @page { size: letter portrait; margin: 15mm; }
        body { margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 11px; color: #000; }
        .annex-header { text-align: center; font-weight: bold; font-size: 13px; border-bottom: 2px solid #333; padding-bottom: 6px; margin-bottom: 14px; }
        .annex-body p { margin: 0 0 7px 0; }
        .annex-body strong, .annex-body b { font-weight: bold; }
        .annex-body h1, .annex-body h2 { font-size: 13px; margin: 12px 0 5px; }
        .annex-body h3, .annex-body h4, .annex-body h5 { font-size: 11px; margin: 10px 0 4px; }
        .annex-body ul, .annex-body ol { margin: 0 0 7px 0; padding-left: 18px; }
        .annex-body li { margin-bottom: 3px; }
        .annex-body table { border-collapse: collapse; width: 100%; margin-bottom: 8px; }
        .annex-body td, .annex-body th { border: 1px solid #999; padding: 4px 6px; }
    </style>
</head>
<body>
{{-- position:fixed is a direct child of <body> in this standalone view, so DomPDF repeats it on every page --}}
<div style="position: fixed; top: -10mm; left: 0; right: 0;
            font-family: Arial, sans-serif; font-size: 9pt; color: #444;
            border-bottom: 1px solid #bbb; padding-bottom: 2px;">
    <strong>Pedido No. {{ str_pad($purchaseOrder->folio ?? $purchaseOrder->id, 5, '0', STR_PAD_LEFT) }}</strong>
</div>
@include('purchase_orders.annexes.contrato', ['annex' => $annex])
</body>
</html>
