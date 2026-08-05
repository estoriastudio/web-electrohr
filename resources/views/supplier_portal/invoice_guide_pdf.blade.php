<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instructivo de Carga de Facturas</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 12px;
            line-height: 1.55;
            margin: 24px;
        }

        h1 {
            font-size: 20px;
            margin-bottom: 4px;
            color: #0f172a;
        }

        h2 {
            font-size: 14px;
            margin-top: 20px;
            margin-bottom: 8px;
            color: #0f172a;
        }

        p {
            margin: 0 0 8px;
        }

        ul, ol {
            margin: 0 0 10px 18px;
            padding: 0;
        }

        li {
            margin-bottom: 6px;
        }

        .muted {
            color: #6b7280;
        }

        .box {
            border: 1px solid #dbeafe;
            background: #eff6ff;
            padding: 10px 12px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <h1>Instructivo para Cargar Facturas</h1>
    <p class="muted">Portal de Proveedores SAHR 2.0</p>

    <p>
        Este documento describe los pasos recomendados para subir correctamente una factura al sistema y evitar rechazos por errores de captura.
    </p>

    <h2>1. Antes de iniciar</h2>
    <ul>
        <li>Verifica que la orden de compra esté autorizada y vigente.</li>
        <li>Ten disponibles los archivos de la factura: PDF y XML.</li>
        <li>Confirma que el folio y el monto coincidan con el CFDI.</li>
    </ul>

    <h2>2. Carga de factura</h2>
    <ol>
        <li>Ingresa al módulo de órdenes de compra del portal.</li>
        <li>Selecciona la orden correspondiente y haz clic en subir factura.</li>
        <li>Adjunta el archivo PDF y XML de la factura.</li>
        <li>Completa los datos solicitados y confirma el envío.</li>
    </ol>

    <h2>3. Seguimiento de estatus</h2>
    <ul>
        <li><strong>En proceso:</strong> la factura está en revisión.</li>
        <li><strong>Aprobada:</strong> la factura fue validada correctamente.</li>
        <li><strong>Rechazada:</strong> se detectó un detalle por corregir.</li>
    </ul>

    <div class="box">
        <strong>Recomendación:</strong>
        Si la factura se rechaza, revisa el motivo indicado por el equipo y vuelve a cargar los archivos corregidos para agilizar la aprobación.
    </div>
</body>
</html>
