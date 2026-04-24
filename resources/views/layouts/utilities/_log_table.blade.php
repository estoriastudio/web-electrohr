{{--
    Componente de auditoría por registro.
    Variables requeridas:
      - $model_type : string  (ej. 'Supplier', 'PurchaseOrder')
      - $model_id   : int
--}}
@php
    $logs = \App\Models\Notification::with('user')
        ->where('type', $model_type)
        ->where('model_id', $model_id)
        ->latest()
        ->get();
@endphp

<div class="card mt-3">
    <div class="card-header border-bottom d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="ri-history-line me-1 text-muted"></i> Historial de actividad
        </h5>
        <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">
            {{ $logs->count() }} {{ Str::plural('registro', $logs->count()) }}
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle table-hover table-centered mb-0">
                <thead class="bg-light-subtle">
                    <tr>
                        <th>Acción</th>
                        <th>Descripción</th>
                        <th>Realizado por</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            {{-- Acción --}}
                            <td>
                                @php
                                    $actionMap = [
                                        'create' => ['icon' => 'ri-add-circle-line', 'color' => 'success', 'label' => 'Creación'],
                                        'update' => ['icon' => 'ri-edit-line',        'color' => 'warning', 'label' => 'Actualización'],
                                        'delete' => ['icon' => 'ri-delete-bin-line',  'color' => 'danger',  'label' => 'Eliminación'],
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
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="ri-inbox-line fs-24 d-block mb-1"></i>
                                Sin actividad registrada
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
