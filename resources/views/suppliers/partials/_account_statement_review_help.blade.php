<div class="alert alert-secondary fs-13 mb-3" role="note">
    <div class="fw-semibold mb-1"><i class="ri-information-line me-1" aria-hidden="true"></i>Que significa "Por revisar"</div>
    <p class="mb-2">El sistema coteja, por hito, la suma de los importes de las facturas aceptadas contra el importe de los pagos del hito. Puede haber varias facturas para un mismo hito, pero su suma no debe exceder a los pagos. Si se excede, o si hay una factura con moneda distinta a la de la OC o con nota de credito mayor a su importe, el saldo no se puede calcular y no se incluye en Pendiente ni Vencido.</p>
    <p class="mb-2">Si el pago ya se realizo y falta facturar parte de su importe, el pago aparece como "Pagado sin factura".</p>
    <div class="fw-semibold mb-1">Como sale de "Por revisar"</div>
    <ul class="mb-0 ps-3">
        <li>No se regulariza ni se reclasifica solo: requiere corregir el dato (importe o moneda de la factura, hito ligado o pago del hito).</li>
        <li>Al validar una factura en Facturas, la pantalla muestra la diferencia entre las facturas y los pagos de los hitos seleccionados.</li>
        <li>El estatus no se guarda: se recalcula cada vez que se abre esta pantalla, por lo que al guardar la correccion la factura pasa de inmediato a Pagado, Pendiente o Vencido.</li>
    </ul>
</div>
