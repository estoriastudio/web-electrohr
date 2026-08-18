<?php

namespace App\Services;

use App\Models\Worker;
use App\Models\WorkerGroup;
use App\Models\WorkerGroupMember;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class WorkerGroupMembershipService
{
    public function add(WorkerGroup $workerGroup, Worker $worker, string $joinedAt): void
    {
        DB::transaction(function () use ($workerGroup, $worker, $joinedAt): void {
            $worker = Worker::query()->lockForUpdate()->findOrFail($worker->id);

            if (WorkerGroupMember::query()->where('worker_id', $worker->id)->whereNull('left_at')->exists()) {
                throw new DomainException('El trabajador ya pertenece a una cuadrilla activa.');
            }

            $workerGroup->members()->attach($worker->id, ['joined_at' => $joinedAt]);
        });
    }

    public function remove(WorkerGroup $workerGroup, Worker $worker, string $leftAt): void
    {
        DB::transaction(function () use ($workerGroup, $worker, $leftAt): void {
            $membership = WorkerGroupMember::query()
                ->where('worker_group_id', $workerGroup->id)
                ->where('worker_id', $worker->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if (! $membership) {
                throw new DomainException('El trabajador no pertenece a esta cuadrilla activa.');
            }

            if (Carbon::parse($leftAt)->lt($membership->joined_at)) {
                throw new DomainException('La fecha de salida no puede ser anterior a la fecha de ingreso.');
            }

            $membership->update(['left_at' => $leftAt]);
        });
    }
}