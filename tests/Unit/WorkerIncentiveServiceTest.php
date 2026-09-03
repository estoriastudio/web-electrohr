<?php

namespace Tests\Unit;

use App\Models\Incentive;
use App\Services\PayrollCalculationService;
use App\Services\WorkerIncentiveService;
use Mockery;
use Tests\TestCase;

class WorkerIncentiveServiceTest extends TestCase
{
    public function test_it_calculates_overtime_from_hours_and_hourly_rate(): void
    {
        $service = new WorkerIncentiveService(Mockery::mock(PayrollCalculationService::class));
        $incentive = new Incentive([
            'category' => 'overtime',
            'overtime_hours' => 3.5,
            'overtime_hourly_rate' => 125.75,
        ]);

        $this->assertSame(440.13, $service->amountFor($incentive, 1000));
    }
}