@php
    $dossierLogoPath = public_path('assets/images/logo-dark.png');
    $dossierLogoData = file_exists($dossierLogoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($dossierLogoPath))
        : '';
@endphp
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 12px;">
    <tr>
        <td width="35%" style="vertical-align: middle; padding: 0 12px 0 0;">
            @if ($dossierLogoData)
            <img src="{{ $dossierLogoData }}" alt="ELECTRO SERVICIOS HR"
                 style="max-width: 160px; max-height: 60px;">
            @endif
        </td>
    </tr>
</table>

<div class="annex-body">
    {!! $annex->dossier_html !!}
</div>
