<div class="table-responsive">
    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
        <thead class="bg-light-subtle">
            <tr>
                <th>Urgencia</th>
                <th>Folio</th>
                <th>Proveedor</th>
                <th>Proyecto / Obra</th>
                <th>Hito</th>
                <th>Monto</th>
                <th>Pago / Vencimiento</th>
                <th>Referencia</th>
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
                    $isUrgent   = $milestone->due_date
                                    && $milestone->due_date->lte($urgentDate)
                                    && $payment->status !== 'pagado';

                    $payStatusMap = [
                        'por_autorizar' => ['label' => 'Por autorizar', 'class' => 'bg-warning-subtle text-warning'],
                        'autorizado'    => ['label' => 'Autorizado',    'class' => 'bg-info-subtle text-info'],
                        'pagado'        => ['label' => 'Pagado',         'class' => 'bg-success-subtle text-success'],
                    ];
                    $ps = $payStatusMap[$payment->status] ?? ['label' => $payment->status, 'class' => 'bg-secondary-subtle text-secondary'];
                @endphp
                <tr class="{{ $isUrgent ? 'table-warning' : '' }}" {{ $isUrgent ? 'data-bs-theme="light"' : '' }}>
                    <td>
                        @if ($isUrgent)
                            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">
                                <i class="ri-alarm-warning-line me-1"></i> Urgente
                            </span>
                        @else
                            <span class="text-muted fs-12">—</span>
                        @endif
                    </td>
                    <td class="fw-semibold">
                        {{ $payment->folio }} <br>
                        <a href="{{ route('purchase_orders.show', $order) }}" class="badge bg-light text-secondary border d-inline-flex align-items-center gap-1 mt-1 text-decoration-none fw-normal fs-11">
                            <i class="ri-file-list-3-line"></i> OC #{{ $order->folio }}
                        </a>
                    </td>
                    <td>
                        <a href="{{ route('suppliers.show', $supplier) }}" class="text-dark fw-medium">
                            {{ $supplier->rfc_name ?? $supplier->commercial_name ?? '—' }}
                        </a>
                    </td>
                    <td>
                        @if ($order->projectRelation)
                            <span class="fw-medium">{{ $order->projectRelation->name }}</span>
                            @if ($order->workRelation)
                                <small class="text-muted d-block fs-11">{{ $order->workRelation->name }}</small>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        Hito #{{ $milestone->id }}
                        <small class="text-muted d-block fs-11">
                            {{ $milestone->type === 'anticipo' ? 'Anticipo' : 'Regular' }}
                        </small>
                    </td>
                    <td class="fw-semibold">
                        <span class="d-flex align-items-center gap-1">
                            $
                            {{ number_format($payment->amount, 2) }}
                        </span>
                        <small class="badge bg-secondary-subtle text-secondary fs-10 mt-1">{{ $order->currency }}</small>
                    </td>
                    <td>
                        <div class="d-flex flex-column gap-1">
                            <span class="d-flex align-items-center gap-1 fs-12">
                                <i class="ri-calendar-check-line text-primary"></i>
                                {{ $payment->payment_date->format('d/m/Y') }}
                            </span>
                            @if ($milestone->due_date)
                                @php $duePast = $milestone->due_date->isPast() && $payment->status !== 'pagado'; @endphp
                                <span class="d-flex align-items-center gap-1 fs-12 {{ $duePast ? 'text-danger fw-semibold' : 'text-muted' }}">
                                    <i class="ri-alarm-warning-line {{ $duePast ? 'text-danger' : 'text-muted' }}"></i>
                                    {{ $milestone->due_date->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-muted fs-12">—</span>
                            @endif
                        </div>
                    </td>
                    <td>{{ $payment->reference_number ?? '—' }}</td>
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
                            @if (in_array($payment->status, ['por_autorizar', 'autorizado']))
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
                            @if ($payment->status !== 'por_autorizar')
                                <form action="{{ route('payments.update', $payment) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="por_autorizar">
                                    <button type="submit" class="btn btn-soft-warning btn-sm" title="Revertir estatus"
                                            onclick="return confirm('¿Revertir el estatus a «Por autorizar»?')">
                                        <i class="ri-arrow-go-back-line"></i>
                                    </button>
                                </form>
                            @endif
                            @endhasanyrole

                            {{-- Ver OC --}}
                            <a href="{{ route('purchase_orders.show', $order) }}"
                                class="btn btn-light btn-sm" title="Ver orden de compra">
                                <i class="ri-eye-line"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-5">
                        <i class="ri-check-double-line fs-36 d-block mb-2 text-success"></i>
                        No hay pagos pendientes de autorización.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>