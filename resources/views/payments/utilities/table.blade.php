@php
    $selectedAuthorizationPaymentIds = collect(data_get($authorizationSelectionState ?? [], 'selected_ids', []))
        ->map(fn ($id) => (int) $id)
        ->all();
@endphp

<div class="table-responsive">
    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
        <thead class="bg-light-subtle">
            <tr>
                @role('admin')
                <th style="width:44px;">
                    <input type="checkbox" class="form-check-input" id="selectAllAuthorizationPayments"
                           title="Seleccionar todos los pagos">
                </th>
                @endrole
                <th>Semáforo</th>
                <th>Orden de Compra</th>
                <th>Proveedor</th>
                <th>Proyecto / Obra</th>
                <th>Monto</th>
                <th>Tipo de hito</th>
                <th>Pago / Vencimiento</th>
                <th>Checklist</th>
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
                    $paymentConditions = $order->milestones
                        ->pluck('payment_condition')
                        ->filter()
                        ->unique()
                        ->values();
                    $paymentConditionLabel = $paymentConditions->count() > 1
                        ? 'Ambas'
                        : ($paymentConditions->first() === 'contado' ? 'Contado' : 'Crédito');
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

                    $checklist = $checklists[$payment->milestone_id];
                @endphp
                <tr>
                    @role('admin')
                    <td>
                        <input type="checkbox" class="form-check-input js-authorization-payment-select"
                               value="{{ $payment->id }}" aria-label="Seleccionar pago {{ $payment->folio }}"
                               @checked(in_array($payment->id, $selectedAuthorizationPaymentIds, true))
                               @disabled(!$checklist['complete'])
                               @if (!$checklist['complete']) title="Checklist pendiente: {{ implode(', ', $checklist['pending']) }}" @endif>
                    </td>
                    @endrole
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
                        <br>
                        <small class="badge bg-info-subtle text-info fs-10 mt-1">{{ $paymentConditionLabel }}</small>
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
                        @if ($milestone->is_advance)
                            <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12"><i class="ri-bank-line me-1"></i>Anticipo</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Pago regular</span>
                        @endif
                        <small class="d-block text-muted fs-11 mt-1">{{ ($milestone->payment_condition ?? 'credito') === 'contado' ? 'Contado' : 'Crédito' }}</small>
                    </td>
                    <td>
                        <span class="d-flex align-items-center gap-1 fs-12">
                            <i class="ri-calendar-check-line text-primary"></i>
                            {{ $payment->payment_date->format('d/m/Y') }}
                        </span>
                    </td>
                    <td>
                        @include('purchase_orders.partials._milestone_checklist', ['checklist' => $checklist, 'compact' => true])
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            {{-- Autorizar: solo admin --}}
                            @if (in_array($payment->status, ['por_autorizar', 'pospuesto']))
                                @role('admin')
                                @if ($checklist['complete'])
                                <form action="{{ route('payments.update', $payment) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="autorizado">
                                    <button type="submit" class="btn btn-soft-info btn-sm" title="Autorizar"
                                            onclick="return confirm('¿Autorizar este pago?')">
                                        <i class="ri-check-line"></i> Autorizar
                                    </button>
                                </form>
                                @else
                                {{-- Un botón disabled no dispara tooltip; se coloca en un contenedor que sí lo muestra. --}}
                                <span class="d-inline-block" style="cursor:not-allowed;"
                                      title="Bloqueado: checklist pendiente ({{ implode(', ', $checklist['pending']) }})">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" disabled
                                            style="pointer-events:none; opacity:.65; border-style:dashed;">
                                        <i class="ri-lock-line"></i> Checklist pendiente
                                    </button>
                                </span>
                                @endif
                                @endrole
                            @endif

                            {{-- Marcar como pagado: admin|Pagos --}}
                            @hasanyrole('admin|Pagos')
                            @if ($payment->status === 'autorizado')
                                <form action="{{ route('payments.update', $payment) }}" method="POST" enctype="multipart/form-data">
                                    @csrf @method('PATCH')
                                    <input type="file" name="spei_receipt_file" class="d-none"
                                           accept=".pdf,.jpg,.jpeg,.png,.webp"
                                           onchange="if (this.files.length) this.form.submit()">
                                    <button type="button" class="btn btn-soft-success btn-sm" title="Adjuntar SPEI y marcar como pagado"
                                            onclick="this.form.querySelector('input[type=file]').click()">
                                        <i class="ri-upload-2-line"></i> Subir SPEI
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
                    <td colspan="{{ auth()->user()->hasRole('admin') ? 10 : 9 }}" class="text-center text-muted py-5">
                        <i class="ri-check-double-line fs-36 d-block mb-2 text-success"></i>
                        No hay pagos pendientes de autorización.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>