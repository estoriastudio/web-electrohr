<div class="table-responsive">
    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
        <thead class="bg-light-subtle">
            <tr>
                <th>Semáforo</th>
                <th>Orden de Compra</th>
                <th>Proveedor</th>
                <th>Proyecto / Obra</th>
                <th>Monto</th>
                <th>Pago / Vencimiento</th>
                <th>Estatus</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                @php
                    $milestone  = $payment->milestone;
                    $order      = $milestone->purchaseOrder;
                    $supplier   = $order->supplier;
                    $supplierName = $supplier->rfc_name ?? $supplier->commercial_name ?? '—';
                    $proj = $order->projectRelation?->name ?? $order->project ?? null;
                    $obra = $order->workRelation?->name ?? $order->site ?? null;
                    $today       = \Carbon\Carbon::today();
                    $dueDate     = $milestone->due_date;
                    $isOverdue   = $dueDate && $dueDate->lt($today);
                    $isNearlyDue = $dueDate && !$isOverdue && $dueDate->lte($urgentDate);

                    if ($isOverdue) {
                        $trafficLight = [
                            'label' => 'Vencido',
                            'class' => 'bg-danger-subtle text-danger',
                            'dot'   => '#dc3545',
                            'icon'  => 'ri-alarm-warning-line',
                        ];
                    } elseif ($isNearlyDue) {
                        $trafficLight = [
                            'label' => 'Próximo a vencer',
                            'class' => 'bg-warning-subtle text-warning',
                            'dot'   => '#ffc107',
                            'icon'  => 'ri-time-line',
                        ];
                    } else {
                        $trafficLight = [
                            'label' => 'Al día',
                            'class' => 'bg-success-subtle text-success',
                            'dot'   => '#28a745',
                            'icon'  => 'ri-check-line',
                        ];
                    }

                    $payStatusMap = [
                        'por_autorizar' => ['label' => 'Por autorizar', 'class' => 'bg-warning-subtle text-warning'],
                        'autorizado'    => ['label' => 'Autorizado',    'class' => 'bg-info-subtle text-info'],
                        'pagado'        => ['label' => 'Pagado',         'class' => 'bg-success-subtle text-success'],
                    ];
                    $ps = $payStatusMap[$payment->status] ?? ['label' => $payment->status, 'class' => 'bg-secondary-subtle text-secondary'];
                @endphp
                <tr>
                    <td>
                        <span class="badge {{ $trafficLight['class'] }} py-1 px-2 fs-12">
                            <i class="ri-checkbox-blank-circle-fill me-1" style="color: {{ $trafficLight['dot'] }};"></i>
                            <i class="{{ $trafficLight['icon'] }} me-1"></i>{{ $trafficLight['label'] }}
                        </span>
                    </td>
                    <td class="fw-semibold">
                        <a href="{{ route('purchase_orders.show', $order) }}" class="text-decoration-none" title="Ver OC #{{ $order->folio ?? '—' }}">
                            <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12 font-monospace">OC #{{ $order->folio ?? '—' }}</span>
                        </a>
                    </td>
                    <td>
                        <a href="{{ route('suppliers.show', $supplier) }}" class="text-dark fw-medium text-decoration-none">
                            <span class="hover-marquee d-block" style="--marquee-width:210px;" title="{{ $supplierName }}">
                                <span class="track"><span>{{ $supplierName }}</span><span aria-hidden="true">{{ $supplierName }}</span></span>
                            </span>
                        </a>
                    </td>
                    <td>
                        @if ($proj)
                            <div class="hover-marquee" style="--marquee-width:170px;" title="{{ $proj }}">
                                <span class="track"><span class="fw-medium">{{ $proj }}</span><span aria-hidden="true">{{ $proj }}</span></span>
                            </div>
                        @endif
                        @if ($obra)
                            <small class="text-muted d-block hover-marquee fs-11" style="--marquee-width:170px;" title="{{ $obra }}">
                                <span class="track"><span>{{ $obra }}</span><span aria-hidden="true">{{ $obra }}</span></span>
                            </small>
                        @endif
                        @if (!$proj && !$obra)
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="fw-semibold">
                        <span class="d-flex align-items-center gap-1">
                            $
                            {{ number_format($payment->amount, 2) }}
                        </span>
                        <small class="badge bg-secondary-subtle text-secondary fs-10 mt-1">{{ $order->currency }}</small>
                    </td>
                    <td>
                        <span class="d-flex align-items-center gap-1 fs-12">
                            <i class="ri-calendar-check-line text-primary"></i>
                            {{ $payment->payment_date->format('d/m/Y') }}
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ $ps['class'] }} py-1 px-2 fs-12">{{ $ps['label'] }}</span>
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            {{-- Autorizar: solo admin --}}
                            @if ($payment->status === 'por_autorizar')
                                @role('admin')
                                <form action="{{ route('payments.update', $payment) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="autorizado">
                                    <button type="submit" class="btn btn-soft-info btn-sm" title="Autorizar"
                                            onclick="return confirm('¿Autorizar este pago?')">
                                        <i class="ri-check-line"></i> Autorizar
                                    </button>
                                </form>
                                @endrole
                            @endif

                            {{-- Marcar como pagado: admin|Pagos --}}
                            @hasanyrole('admin|Pagos')
                            @if ($payment->status === 'autorizado')
                                <form action="{{ route('payments.update', $payment) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="pagado">
                                    <button type="submit" class="btn btn-soft-success btn-sm" title="Marcar como pagado"
                                            onclick="return confirm('¿Marcar como PAGADO? Esto actualizará el saldo cubierto del hito.')">
                                        <i class="ri-money-dollar-circle-line"></i> Pagado
                                    </button>
                                </form>
                            @endif

                            {{-- Revertir a por autorizar --}}
                            @role('admin')
                            @if (in_array($payment->status, ['autorizado', 'rechazado']))
                                <form action="{{ route('payments.update', $payment) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="por_autorizar">
                                    <button type="submit" class="btn btn-soft-warning btn-sm" title="Revertir estatus"
                                            onclick="return confirm('¿Revertir el estatus a «Por autorizar»?')">
                                        <i class="ri-arrow-go-back-line"></i>
                                    </button>
                                </form>
                            @endif
                            @endrole
                            @endhasanyrole
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="ri-check-double-line fs-36 d-block mb-2 text-success"></i>
                        No hay pagos pendientes de autorización.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>