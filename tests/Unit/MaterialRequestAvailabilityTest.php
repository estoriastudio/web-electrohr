<?php

namespace Tests\Unit;

use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\MaterialRequestItemProjectWork;
use App\Models\ProjectWork;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class MaterialRequestAvailabilityTest extends TestCase
{
    public function test_request_without_items_has_no_available_quantity(): void
    {
        $materialRequest = $this->materialRequestWith([], [63, 64]);

        $this->assertFalse($materialRequest->hasAvailableQuantityForProjectWorks());
    }

    public function test_request_with_uncommitted_quantity_for_its_work_is_available(): void
    {
        $item = new MaterialRequestItem(['quantity' => 0]);
        $item->setRelation('workQuantities', new Collection([
            new MaterialRequestItemProjectWork([
                'project_work_id' => 63,
                'quantity' => 4,
                'is_committed' => false,
            ]),
        ]));

        $materialRequest = $this->materialRequestWith([$item], [63]);

        $this->assertTrue($materialRequest->hasAvailableQuantityForProjectWorks());
    }

    public function test_request_with_only_committed_quantities_has_no_available_quantity(): void
    {
        $item = new MaterialRequestItem(['quantity' => 0]);
        $item->setRelation('workQuantities', new Collection([
            new MaterialRequestItemProjectWork([
                'project_work_id' => 63,
                'quantity' => 4,
                'is_committed' => true,
            ]),
        ]));

        $materialRequest = $this->materialRequestWith([$item], [63]);

        $this->assertFalse($materialRequest->hasAvailableQuantityForProjectWorks());
    }

    private function materialRequestWith(array $items, array $projectWorkIds): MaterialRequest
    {
        $materialRequest = new MaterialRequest();
        $materialRequest->setRelation('items', new Collection($items));
        $materialRequest->setRelation('projectWorks', new Collection(array_map(function (int $projectWorkId) {
            $projectWork = new ProjectWork();
            $projectWork->id = $projectWorkId;

            return $projectWork;
        }, $projectWorkIds)));

        return $materialRequest;
    }
}