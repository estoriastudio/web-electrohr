@php
    $statusMeta = [
        'pendiente' => ['label' => 'Pendiente', 'icon' => 'ri-time-line', 'description' => 'La factura tiene saldo pendiente de pago.'],
        'pagado' => ['label' => 'Pagado', 'icon' => 'ri-checkbox-circle-line', 'description' => 'La factura esta pagada por completo.'],
        'vencido' => ['label' => 'Vencido', 'icon' => 'ri-alarm-warning-line', 'description' => 'La fecha de pago ya paso y la factura tiene saldo pendiente.'],
        'sin_factura' => ['label' => 'Pagado sin factura', 'icon' => 'ri-file-warning-line', 'description' => 'El pago ya se realizo, pero falta su factura validada.'],
        'conciliacion' => ['label' => 'Por revisar', 'icon' => 'ri-search-eye-line', 'description' => 'Falta confirmar cuanto de este pago corresponde a cada factura, o revisar sus datos.'],
    ][$status];
@endphp
<span class="statement-status statement-status--{{ $status }}" title="{{ $statusMeta['description'] }}">
    <i class="{{ $statusMeta['icon'] }}" aria-hidden="true"></i>
    <span>{{ $statusMeta['label'] }}</span>
</span>