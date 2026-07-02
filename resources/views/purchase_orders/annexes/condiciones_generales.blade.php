@php
    $ocFolio     = $purchaseOrder->folio ?? $purchaseOrder->id;
    $penPct      = $annex->penalidad_porcentaje ?? '[PENALIDAD EN PORCENTAJE]';
    $penNum      = $annex->penalidad_numero     ?? '[PENALIDAD EN NUMERO]';
    $nomAcep     = $annex->nombre_aceptacion    ?? '[NOMBRE DE ACEPTACIÓN]';
    $clienteName = $annex->client_name   ?: 'ELECTRO SERVICIOS HR, S.A. DE C.V.';
    $provName    = $annex->provider_name ?: 'LA CONTRATADA';

    $mdRaw = file_get_contents(public_path('document_templates/CONDICIONES GENERALES DE COMPRA.md'));
    $html  = \Illuminate\Support\Str::markdown($mdRaw);

    // Replace placeholders (the .md uses \[...\] which renders as [...] after parsing)
    $html = str_replace('[NUMERO DE ORDEN DE COMPRA]', htmlspecialchars((string) $ocFolio), $html);
    $html = str_replace('[PENALIDAD EN PORCENTAJE]',   htmlspecialchars((string) $penPct),  $html);
    $html = str_replace('[PENALIDAD EN NUMERO]',       htmlspecialchars((string) $penNum),  $html);
    $html = str_replace('[NOMBRE DE ACEPTACIÓN]',      htmlspecialchars((string) $nomAcep), $html);
@endphp
<div class="annex-header">CONDICIONES GENERALES DE COMPRA &nbsp;&mdash;&nbsp; OC #{{ $ocFolio }}</div>

<div class="annex-body">
    {!! $html !!}
</div>
