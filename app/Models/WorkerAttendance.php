<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkerAttendance extends Model
{
    /** Catálogo de incidencias: clave => [letra, etiqueta, color de badge]. */
    public const INCIDENTS = [
        'pending' => ['P', 'Pendiente', 'secondary'],
        'attendance' => ['A', 'Asistencia', 'success'],
        'absence' => ['F', 'Falta', 'danger'],
        'rest' => ['D', 'Descanso', 'info'],
        'rest_worked' => ['DT', 'Descanso trabajado', 'primary'],
        'vacation' => ['V', 'Vacaciones', 'info'],
        'vacation_worked' => ['VT', 'Vacaciones trabajadas', 'primary'],
        'day_off_a' => ['LA', 'Libranza A', 'warning'],
        'day_off_b' => ['LB', 'Libranza B', 'warning'],
        'day_off_c' => ['LC', 'Libranza C', 'warning'],
    ];

    /** Incidencias que cuentan como día trabajado (solo ellas admiten apoyo). */
    public const WORKED = ['attendance', 'rest_worked', 'vacation_worked'];

    protected $fillable = [
        'worker_id',
        'date',
        'project_work_id',
        'worker_group_id',
        'incident',
        'captured_by',
        'captured_at',
    ];

    protected $casts = [
        'date' => 'date',
        'captured_at' => 'datetime',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }

    public function workerGroup(): BelongsTo
    {
        return $this->belongsTo(WorkerGroup::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AttendanceHistory::class);
    }

    public static function label(string $incident): string
    {
        return self::INCIDENTS[$incident][1] ?? $incident;
    }

    public function isWorked(): bool
    {
        return in_array($this->incident, self::WORKED, true);
    }
}
