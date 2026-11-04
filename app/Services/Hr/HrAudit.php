<?php

namespace App\Services\Hr;

use App\Models\HrAuditLog;
use App\Models\Worker;
use Illuminate\Support\Facades\Auth;

/**
 * Bitácora del proceso de asistencia y nómina (HR-005). No sustituye al
 * NotificationService: los controllers y componentes notifican por separado.
 */
class HrAudit
{
    public static function log(
        string $action,
        string $entity,
        string|int|null $entityId = null,
        Worker|int|null $worker = null,
        mixed $before = null,
        mixed $after = null,
        ?string $reason = null,
        bool $sensitive = false,
    ): HrAuditLog {
        return HrAuditLog::create([
            'user_id' => Auth::id(),
            'role' => HrPermissions::roleOf(),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'worker_id' => $worker instanceof Worker ? $worker->id : $worker,
            'before' => self::encode($before),
            'after' => self::encode($after),
            'reason' => $reason,
            'sensitive' => $sensitive,
        ]);
    }

    private static function encode(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
