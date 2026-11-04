<?php

namespace App\Services\Hr;

use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerFile;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Qué secciones del perfil del trabajador ve cada rol y marca de expediente
 * incompleto (HR-020). Clase de servicio simple: no es una Policy ni un Gate.
 */
class WorkerProfileService
{
    /** Atributos del trabajador que solo ve quien tiene seeAmounts. */
    private const AMOUNT_ATTRIBUTES = ['weekly_salary', 'payment_type'];

    /**
     * Arma el perfil según los permisos del usuario. Lo que el usuario no puede ver
     * no llega a la vista: se quita del modelo y no se consulta.
     *
     * @return array{canSeeAmounts: bool, canSeeIncentives: bool, receipts: Collection}
     */
    public function profile(Worker $worker, ?User $user): array
    {
        $canSeeAmounts = HrPermissions::can('seeAmounts', $user);
        $canSeeIncentives = HrPermissions::can('seeIncentives', $user);

        $worker->load([
            'positionCategory',
            'workerGroup',
            'registeredByUser',
            'file',
            'dc3s',
            'terminations.projectWork',
            'terminations.positionCategory',
        ]);

        if (! $canSeeAmounts) {
            foreach (self::AMOUNT_ATTRIBUTES as $attribute) {
                $worker->offsetUnset($attribute);
            }
        }

        return [
            'canSeeAmounts' => $canSeeAmounts,
            'canSeeIncentives' => $canSeeIncentives,
            'receipts' => $canSeeAmounts ? $this->receipts($worker) : collect(),
        ];
    }

    /**
     * Recibos de periodos autorizados. Se alimenta de las filas congeladas de la
     * nómina nueva (HR-013); hasta entonces no hay recibos que mostrar.
     *
     * @return Collection<int, array{id: int, period_start: CarbonInterface, project_name: string, amount: float}>
     */
    public function receipts(Worker $worker): Collection
    {
        return collect();
    }

    /** @return array<int, string> columnas obligatorias del expediente que faltan. */
    public function missingRequiredDocuments(Worker $worker): array
    {
        $file = $worker->file;

        return collect(WorkerFile::REQUIRED_DOCUMENT_COLUMNS)
            ->filter(fn (string $column) => blank($file?->{$column}))
            ->values()
            ->all();
    }

    public function isFileComplete(Worker $worker): bool
    {
        return $this->missingRequiredDocuments($worker) === [];
    }

    /**
     * Limpia la marca de expediente incompleto cuando ya están los 9 documentos
     * obligatorios (Título y Cédula son opcionales). Devuelve true si la limpió.
     */
    public function clearIncompleteMarkIfComplete(Worker $worker): bool
    {
        $worker->unsetRelation('file');

        if (! $worker->file_incomplete || ! $this->isFileComplete($worker)) {
            return false;
        }

        $worker->update(['file_incomplete' => false]);

        return true;
    }

    /**
     * Activa a un trabajador (HR-020). Sin expediente completo solo aplica a pre-registros
     * y exige un motivo; deja la marca file_incomplete y la bitácora. La asignación
     * provisional del alta en obra (HR-006) deja de serlo.
     *
     * @return array{ok: bool, message: string, complete: bool}
     */
    public function activate(Worker $worker, bool $withoutCompleteFile, ?string $reason = null): array
    {
        if ($worker->status === 'terminated') {
            return ['ok' => false, 'message' => 'No se puede activar a un trabajador dado de baja.', 'complete' => false];
        }

        $worker->unsetRelation('file');
        $isFileComplete = $this->isFileComplete($worker);

        if (! $isFileComplete && ! $withoutCompleteFile) {
            return ['ok' => false, 'message' => 'No se puede dar de alta hasta que el expediente esté completo.', 'complete' => false];
        }

        if (! $isFileComplete && $worker->status !== 'pre_registered') {
            return ['ok' => false, 'message' => 'Solo un pre-registro puede activarse sin expediente completo.', 'complete' => false];
        }

        $previousStatus = $worker->status;
        $worker->update(['status' => 'active', 'file_incomplete' => ! $isFileComplete]);
        $worker->assignments()->where('provisional', true)->update(['provisional' => false]);

        if (! $isFileComplete) {
            HrAudit::log(
                'Activación sin expediente completo',
                'Trabajador',
                $worker->id,
                $worker,
                $previousStatus,
                'active (expediente incompleto)',
                $reason,
            );
        }

        return [
            'ok' => true,
            'message' => $isFileComplete ? 'Trabajador dado de alta correctamente.' : 'Trabajador dado de alta sin expediente completo.',
            'complete' => $isFileComplete,
        ];
    }
}
