<?php

namespace App\Services;

use App\Models\WorkerGroup;
use Illuminate\Support\Facades\DB;

class WorkerGroupRelocationService
{
    public function relocate(WorkerGroup $workerGroup, int $projectWorkId): WorkerGroup
    {
        return DB::transaction(function () use ($workerGroup, $projectWorkId): WorkerGroup {
            $workerGroup = WorkerGroup::query()->lockForUpdate()->findOrFail($workerGroup->id);
            $workerGroup->update(['project_work_id' => $projectWorkId]);

            return $workerGroup->fresh('projectWork');
        });
    }
}