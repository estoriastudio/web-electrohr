<div class="table-responsive">
    <table class="table align-middle table-hover table-centered mb-0">
        <thead class="bg-light-subtle">
            <tr>
                <th>Módulo</th>
                <th>Acción</th>
                <th>Descripción</th>
                <th>Realizado por</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($notifications as $log)
                <tr>
                    {{-- Módulo --}}
                    <td>
                        @php
                            $moduleLabels = [
                                'Supplier'      => ['label' => 'Proveedor',         'color' => 'primary'],
                                'PurchaseOrder' => ['label' => 'Orden de compra',   'color' => 'info'],
                            ];
                            $module = $moduleLabels[$log->type] ?? ['label' => $log->type, 'color' => 'secondary'];
                        @endphp
                        <span class="badge bg-{{ $module['color'] }}-subtle text-{{ $module['color'] }} py-1 px-2 fs-12">
                            {{ $module['label'] }} #{{ $log->model_id }}
                        </span>
                    </td>

                    {{-- Acción --}}
                    <td>
                        @php
                            $actionMap = [
                                'create' => ['icon' => 'ri-add-circle-line',    'color' => 'success', 'label' => 'Creación'],
                                'update' => ['icon' => 'ri-edit-line',          'color' => 'warning', 'label' => 'Actualización'],
                                'delete' => ['icon' => 'ri-delete-bin-line',    'color' => 'danger',  'label' => 'Eliminación'],
                            ];
                            $action = $actionMap[$log->model_action] ?? ['icon' => 'ri-information-line', 'color' => 'secondary', 'label' => $log->model_action];
                        @endphp
                        <span class="text-{{ $action['color'] }} fw-medium fs-13">
                            <i class="{{ $action['icon'] }} me-1"></i>{{ $action['label'] }}
                        </span>
                    </td>

                    {{-- Descripción --}}
                    <td class="text-muted fs-13">{{ $log->data }}</td>

                    {{-- Usuario --}}
                    <td class="fs-13">
                        @if ($log->user)
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-xs bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                    <span class="text-primary fw-semibold" style="font-size: 10px;">
                                        {{ strtoupper(substr($log->user->name, 0, 1)) }}
                                    </span>
                                </div>
                                {{ $log->user->name }}
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Fecha --}}
                    <td class="text-muted fs-12 text-nowrap">
                        {{ $log->created_at->format('d/m/Y H:i') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <i class="ri-inbox-line fs-24 d-block mb-1"></i>
                        Sin registros de actividad
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
