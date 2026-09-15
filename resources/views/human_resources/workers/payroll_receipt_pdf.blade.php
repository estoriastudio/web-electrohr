<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de nómina</title>
    <style>
        @page { margin: 18mm; }
        body { font-family: Arial, sans-serif; color: #1f2937; font-size: 10px; }
        .header { border-bottom: 2px solid #1d4ed8; padding-bottom: 12px; margin-bottom: 18px; }
        .title { color: #1d4ed8; font-size: 19px; font-weight: bold; margin: 0 0 4px; }
        .muted { color: #64748b; }
        .meta, .detail { width: 100%; border-collapse: collapse; }
        .meta td { width: 50%; padding: 4px 0; vertical-align: top; }
        .detail { margin-top: 18px; }
        .detail th, .detail td { border: 1px solid #cbd5e1; padding: 7px 8px; }
        .detail th { background: #eff6ff; text-align: left; }
        .amount { text-align: right; white-space: nowrap; }
        .deduction { color: #b91c1c; }
        .total { margin-top: 18px; margin-left: auto; width: 45%; border-collapse: collapse; }
        .total td { padding: 8px; border: 1px solid #1d4ed8; }
        .total .label { background: #dbeafe; font-weight: bold; }
        .total .value { font-size: 14px; font-weight: bold; text-align: right; }
        .footer { margin-top: 42px; padding-top: 10px; border-top: 1px solid #cbd5e1; text-align: center; color: #64748b; font-size: 9px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Recibo de nómina</div>
        <span class="muted">Pago semanal realizado</span>
    </div>

    <table class="meta">
        <tr><td><strong>Trabajador:</strong> {{ $worker->first_name }} {{ $worker->last_name }}</td><td><strong>No. de empleado:</strong> {{ $worker->nss ?: 'No registrado' }}</td></tr>
        <tr><td><strong>Semana:</strong> {{ $payrollLine->payrollPeriod->week_number }}/{{ $payrollLine->payrollPeriod->year }}</td><td><strong>Periodo:</strong> {{ $payrollLine->payrollPeriod->start_date->format('d/m/Y') }} al {{ $payrollLine->payrollPeriod->end_date->format('d/m/Y') }}</td></tr>
        <tr><td><strong>Puesto:</strong> {{ $payrollLine->positionCategory?->name ?: 'No asignado' }}</td><td><strong>Obra:</strong> {{ $payrollLine->projectWork?->name ?: 'No asignada' }}</td></tr>
    </table>

    <table class="detail">
        <thead><tr><th>Concepto</th><th class="amount">Importe</th></tr></thead>
        <tbody>
            <tr><td>Sueldo base</td><td class="amount">${{ number_format((float) $payrollLine->base_salary, 2) }}</td></tr>
            <tr><td>Comida ({{ $payrollLine->meal_days }} día(s))</td><td class="amount">${{ number_format((float) $payrollLine->meal_total_amount, 2) }}</td></tr>
            <tr><td>Pago domingo</td><td class="amount">${{ number_format((float) $payrollLine->sunday_amount, 2) }}</td></tr>
            <tr><td>Incentivos</td><td class="amount">${{ number_format((float) $payrollLine->incentive_amount, 2) }}</td></tr>
            <tr><td>Extras</td><td class="amount">${{ number_format((float) $payrollLine->extras_amount, 2) }}</td></tr>
            <tr><td class="deduction">Inasistencias ({{ $payrollLine->absence_days }} día(s))</td><td class="amount deduction">-${{ number_format((float) $payrollLine->absence_amount, 2) }}</td></tr>
            <tr><td class="deduction">Material extraviado</td><td class="amount deduction">-${{ number_format((float) $payrollLine->lost_material_amount, 2) }}</td></tr>
            <tr><td class="deduction">Préstamo</td><td class="amount deduction">-${{ number_format((float) $payrollLine->loan_amount, 2) }}</td></tr>
            <tr><td class="deduction">INFONAVIT</td><td class="amount deduction">-${{ number_format((float) $payrollLine->infonavit_amount, 2) }}</td></tr>
            <tr><td class="deduction">Caja de ahorro</td><td class="amount deduction">-${{ number_format((float) $payrollLine->savings_fund_amount, 2) }}</td></tr>
        </tbody>
    </table>

    <table class="total"><tr><td class="label">Total pagado</td><td class="value">${{ number_format((float) $payrollLine->total_amount, 2) }}</td></tr></table>
    <div class="footer">Este recibo refleja el cálculo de nómina de la semana indicada.</div>
</body>
</html>