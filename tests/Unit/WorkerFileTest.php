<?php

namespace Tests\Unit;

use App\Models\WorkerFile;
use Tests\TestCase;

class WorkerFileTest extends TestCase
{
    public function test_it_is_complete_only_when_all_required_documents_are_present(): void
    {
        $workerFile = new WorkerFile;

        $this->assertFalse($workerFile->isComplete());

        foreach (WorkerFile::DOCUMENT_COLUMNS as $column) {
            $workerFile->{$column} = "worker-files/1/{$column}.pdf";
        }

        $this->assertTrue($workerFile->isComplete());
    }
}