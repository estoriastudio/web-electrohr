<?php

namespace App\Services;

use App\Models\Worker;
use App\Models\WorkerTermination;
use Illuminate\Support\Facades\DB;

class WorkerTerminationService
{
    public function terminate(Worker $worker, array $data): WorkerTermination
    {
        return DB::transaction(function () use ($worker, $data): WorkerTermination {
            $worker = Worker::query()->lockForUpdate()->findOrFail($worker->id);
            $projectWorkId = $data['project_work_id'] ?? $worker->currentProjectWork()?->id;

            $termination = WorkerTermination::create([
                'worker_id' => $worker->id,
                'project_work_id' => $projectWorkId,
                'job_title' => $data['job_title'] ?? $worker->job_title,
                'salary' => $data['salary'] ?? $worker->weekly_salary,
                'termination_type' => $data['termination_type'],
                'reason' => $data['reason'] ?? null,
                'termination_date' => $data['termination_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            $worker->update(['status' => 'terminated']);

            return $termination;
        });
    }
}