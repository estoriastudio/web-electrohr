@php
    $pr             = $purchaseRequest;
    $logoPath       = public_path('assets/images/logo-dark.png');
    $logoData       = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : '';
    $folio          = $pr->folio ?? '—';
    $codigo         = $pr->code ?? '—';
    $proyecto       = $pr->project?->name ?? '—';
    $obra           = $pr->projectWork?->name ?? '—';
    $zona           = $pr->zone ?? '—';
    $dirEntrega     = $pr->delivery_address ?? '—';
    $fechaSolicitud = $pr->request_date?->format('d/m/Y') ?? '—';
    $fechaNecesidad = $pr->need_date?->format('d/m/Y') ?? '—';
    $descripcion    = $pr->short_description ?? '—';
    $items          = $pr->items;
    $elaboradaPor   = $pr->materialRequest?->requestedBy?->name
                        ?? $pr->materialRequest?->requested_by
                        ?? $pr->elaborated_by
                        ?? '—';
                    
    $obsArray       = is_array($pr->observations) ? $pr->observations : [];
    $obsTexto       = implode(' | ', array_column($obsArray, 'text'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de COMPRA</title>
    <style>
        @page { size: letter portrait; margin: 15mm; }
        body { margin: 0; padding: 0; }
    </style>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 11px; color: #000;">

<!-- Tabla principal contenedora -->
<table width="750" cellpadding="0" cellspacing="0" border="0" style="width: 750px; margin: 0 auto;">

    <!-- FILA 1: Encabezado - Logo + Título + Folio + Código -->
    <tr>
        <td style="padding: 0;">
            <table width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #000;">
                <tr>
                    <!-- Logo -->
                    <td width="20%" style="border: 1px solid #000; padding: 8px; text-align: center; vertical-align: middle;">
                        @if($logoData)
                        <img src="{{ $logoData }}" alt="HR ELECTRO SERVICIOS" style="max-width: 100px; max-height: 55px;" />
                        @endif
                    </td>
                    <!-- Título -->
                    <td width="40%" style="border: 1px solid #000; padding: 8px; text-align: center; vertical-align: middle; font-size: 16px; font-weight: bold;">
                        Solicitud de COMPRA
                    </td>
                    <!-- Folio -->
                    <td width="18%" style="border: 1px solid #000; padding: 8px; text-align: center; vertical-align: middle;">
                        <strong>Folio: {{ $folio }}</strong>
                    </td>
                    <!-- Código -->
                    <td width="22%" style="border: 1px solid #000; padding: 8px; text-align: center; vertical-align: middle; font-size: 9px;">
                        Codigo {{ $codigo }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- FILA 2: Proyecto -->
    <tr>
        <td style="padding: 0;">
            <table width="100%" cellpadding="5" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #000; margin-top: -1px;">
                <tr>
                    <td style="border: 1px solid #000; padding: 5px 8px;">
                        <strong>Proyecto:</strong> {{ $proyecto }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- FILA 3: Obra -->
    <tr>
        <td style="padding: 0;">
            <table width="100%" cellpadding="5" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #000; margin-top: -1px;">
                <tr>
                    <td style="border: 1px solid #000; padding: 5px 8px;">
                        <strong>Obra:</strong> {{ $obra }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- FILA 4: Zona -->
    <tr>
        <td style="padding: 0;">
            <table width="100%" cellpadding="5" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #000; margin-top: -1px;">
                <tr>
                    <td style="border: 1px solid #000; padding: 5px 8px;">
                        <strong>Zona:</strong> {{ $zona }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- FILA 5: Dirección de entrega -->
    <tr>
        <td style="padding: 0;">
            <table width="100%" cellpadding="5" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #000; margin-top: -1px;">
                <tr>
                    <td style="border: 1px solid #000; padding: 5px 8px;">
                        <strong>Dirección de entrega:</strong> {{ $dirEntrega }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- FILA 6: Fechas -->
    <tr>
        <td style="padding: 0;">
            <table width="100%" cellpadding="5" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #000; margin-top: -1px;">
                <tr>
                    <!-- Espacio izquierdo vacío -->
                    <td width="30%" style="border: 1px solid #000; padding: 5px 8px; border-right: none;">&nbsp;</td>
                    <!-- Fecha Solicitud -->
                    <td width="23%" style="border: 1px solid #000; padding: 5px 8px; text-align: center;">
                        <strong>Fecha Solicitud:</strong> {{ $fechaSolicitud }}
                    </td>
                    <!-- Fecha Necesidad -->
                    <td width="23%" style="border: 1px solid #000; padding: 5px 8px; text-align: center;">
                        <strong>Fecha de Necesidad:</strong> {{ $fechaNecesidad }}
                    </td>
                    <!-- Espacio derecho vacío -->
                    <td width="24%" style="border: 1px solid #000; padding: 5px 8px; border-left: none;">&nbsp;</td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- FILA 7: Categoría de Suministros -->
    <tr>
        <td style="padding: 0;">
            <table width="100%" cellpadding="5" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #000; margin-top: -1px;">
                <tr>
                    <td style="border: 1px solid #000; padding: 5px 8px;">
                        <strong>Categoría de Suministros:</strong> {{ $descripcion }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- FILA 8: Tabla de COMPRAes -->
    <tr>
        <td style="padding: 0;">
            <table width="100%" cellpadding="5" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #000; margin-top: -1px;">
                <!-- Encabezados -->
                <tr style="background-color: #f0f0f0;">
                    <th style="border: 1px solid #000; padding: 6px; text-align: center; width: 6%;">No.</th>
                    <th style="border: 1px solid #000; padding: 6px; text-align: center; width: 15%;">Codigo.</th>
                    <th style="border: 1px solid #000; padding: 6px; text-align: center; width: 57%;">Descripción.</th>
                    <th style="border: 1px solid #000; padding: 6px; text-align: center; width: 12%;">Cantidad.</th>
                    <th style="border: 1px solid #000; padding: 6px; text-align: center; width: 10%;">Unidad.</th>
                </tr>
                <!-- Filas de COMPRAes (loop Laravel) -->
                @foreach($items as $i => $item)
                <tr>
                    <td style="border: 1px solid #000; padding: 5px; text-align: center;">{{ $i + 1 }}</td>
                    <td style="border: 1px solid #000; padding: 5px; text-align: center;">{{ $item->code }}</td>
                    <td style="border: 1px solid #000; padding: 5px;">{{ $item->description }}</td>
                    <td style="border: 1px solid #000; padding: 5px; text-align: center;">{{ $item->purchase_quantity }}</td>
                    <td style="border: 1px solid #000; padding: 5px; text-align: center;">{{ $item->unit }}</td>
                </tr>
                @endforeach
                <!-- Filas vacías de relleno para dar altura al documento -->
                @for($i = count($items); $i < 15; $i++)
                <tr style="height: 22px;">
                    <td style="border: 1px solid #000;">&nbsp;</td>
                    <td style="border: 1px solid #000;"></td>
                    <td style="border: 1px solid #000;"></td>
                    <td style="border: 1px solid #000;"></td>
                    <td style="border: 1px solid #000;"></td>
                </tr>
                @endfor
            </table>
        </td>
    </tr>

    <!-- ESPACIO -->
    <tr><td style="height: 30px;"></td></tr>

    <!-- FILA 9: Observaciones -->
    <tr>
        <td style="padding: 0 0 5px 0;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding: 4px 0 2px 0; font-weight: bold;">Observaciones</td>
                </tr>
                <tr>
                    <td style="padding: 2px 0;">{{ $obsTexto }}</td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- ESPACIO -->
    <tr><td style="height: 40px;"></td></tr>

    <!-- FILA 10: Solicitud elaborada por -->
    <tr>
        <td style="padding: 0;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding: 4px 0; font-weight: bold;">
                        Solicitud Elaborada por: {{ $elaboradaPor }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

</table>
</body>
</html>