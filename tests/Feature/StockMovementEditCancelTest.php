<?php

namespace Tests\Feature;

use App\Models\StockEntry;
use App\Models\StockExit;
use App\Services\NotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockMovementEditCancelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Auth\Middleware\Authenticate::class, \Spatie\Permission\Middleware\RoleMiddleware::class, \App\Http\Middleware\EnsureModuleAccess::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

        Schema::create('concepts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description');
            $table->string('unit')->nullable();
        });
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->softDeletes();
        });
        Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('concept_id')->nullable();
            $table->unsignedBigInteger('tool_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('entry_type');
            $table->string('purchase_reference')->nullable();
            $table->decimal('quantity', 14, 3);
            $table->date('received_at');
            $table->string('invoice_file_name')->nullable();
            $table->string('invoice_file_path')->nullable();
            $table->string('invoice_disk')->nullable();
            $table->boolean('is_adjustment')->default(false);
            $table->text('observations')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_entry_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_entry_id');
            $table->unsignedBigInteger('concept_id');
            $table->unsignedSmallInteger('line_number');
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
        });
        Schema::create('stock_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_entry_id');
            $table->unsignedBigInteger('concept_id');
            $table->string('certificate_type');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('disk')->default('s3');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_exits', function (Blueprint $table) {
            $table->id();
            foreach (['concept_id', 'tool_id', 'recipient_worker_id', 'project_id', 'project_work_id', 'return_stock_entry_id', 'created_by'] as $column) {
                $table->unsignedBigInteger($column)->nullable();
            }
            $table->string('exit_type');
            $table->string('voucher_number')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('status');
            $table->decimal('quantity', 14, 3)->default(1);
            $table->date('exited_at');
            $table->date('expected_return_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->boolean('is_adjustment')->default(false);
            $table->text('observations')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_exit_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_exit_id');
            $table->unsignedBigInteger('concept_id');
            $table->unsignedSmallInteger('line_number');
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
        });

        DB::table('concepts')->insert(['id' => 1, 'code' => 'SUM-001', 'description' => 'Cable', 'unit' => 'm']);
        DB::table('workers')->insert(['id' => 1, 'first_name' => 'Juan', 'last_name' => 'Perez']);
    }

    private function entry(float $quantity, array $attributes = []): StockEntry
    {
        $entry = StockEntry::create($attributes + [
            'entry_type' => 'purchase', 'quantity' => $quantity, 'received_at' => '2026-09-01',
            'invoice_file_path' => 'x.pdf', 'invoice_disk' => 's3', 'created_by' => 1,
        ]);
        $entry->items()->create(['concept_id' => 1, 'line_number' => 1, 'quantity' => $quantity]);

        return $entry;
    }

    private function exit(float $quantity, array $attributes = []): StockExit
    {
        $exit = StockExit::create($attributes + [
            'exit_type' => 'definitive', 'voucher_number' => 'V-1', 'recipient_name' => 'Pedro',
            'quantity' => $quantity, 'exited_at' => '2026-09-02', 'status' => 'completed', 'created_by' => 1,
        ]);
        $exit->items()->create(['concept_id' => 1, 'line_number' => 1, 'quantity' => $quantity]);

        return $exit;
    }

    public function test_entry_can_be_edited_and_the_change_is_audited(): void
    {
        $entry = $this->entry(10);
        $item = $entry->items->first();
        $this->mock(NotificationService::class)->shouldReceive('send')->once()->withArgs(
            fn (array $payload) => $payload['model_action'] === 'update'
                && $payload['type'] === 'StockEntry'
                && str_contains($payload['data'], 'SUM-001: 10 → 15')
        );

        $this->put(route('stocks.entries.update', $entry), [
            'received_at' => '2026-09-05', 'purchase_reference' => 'OC-9', 'observations' => 'Corregida',
            'items' => [$item->id => ['quantity' => 15]],
        ])->assertRedirect(route('stocks.entries.index'))->assertSessionHasNoErrors();

        $entry->refresh();
        $this->assertSame('OC-9', $entry->purchase_reference);
        $this->assertSame('2026-09-05', $entry->received_at->toDateString());
        $this->assertEquals(15, $entry->quantity);
        $this->assertEquals(15, $item->fresh()->quantity);
    }

    public function test_entry_quantity_cannot_drop_below_what_was_already_issued(): void
    {
        $entry = $this->entry(10);
        $this->exit(8);
        $this->mock(NotificationService::class)->shouldReceive('send')->never();

        $this->put(route('stocks.entries.update', $entry), [
            'received_at' => '2026-09-01', 'items' => [$entry->items->first()->id => ['quantity' => 5]],
        ])->assertSessionHasErrors();

        $this->assertEquals(10, $entry->items->first()->fresh()->quantity);
    }

    public function test_entry_with_dependent_exits_cannot_be_deleted_but_free_one_can(): void
    {
        $entry = $this->entry(10);
        $this->exit(8);
        $this->mock(NotificationService::class)->shouldReceive('send')->once()->withArgs(
            fn (array $payload) => $payload['model_action'] === 'destroy' && $payload['type'] === 'StockEntry'
        );

        $this->delete(route('stocks.entries.destroy', $entry))->assertSessionHasErrors('stock');
        $this->assertNotNull(StockEntry::find($entry->id));

        $free = $this->entry(3);
        $this->delete(route('stocks.entries.destroy', $free))->assertSessionHasNoErrors();
        $this->assertNull(StockEntry::find($free->id));
    }

    public function test_deleting_a_return_entry_reopens_the_loan(): void
    {
        $return = StockEntry::create(['entry_type' => 'tool_return', 'quantity' => 1, 'received_at' => '2026-09-10', 'created_by' => 1]);
        $loan = StockExit::create([
            'exit_type' => 'tool_loan', 'concept_id' => 1, 'voucher_number' => 'L-1', 'recipient_worker_id' => 1,
            'quantity' => 1, 'exited_at' => '2026-09-02', 'expected_return_at' => '2026-09-09',
            'status' => 'returned', 'return_stock_entry_id' => $return->id, 'created_by' => 1,
        ]);
        $this->mock(NotificationService::class)->shouldReceive('send')->once();

        $this->delete(route('stocks.entries.destroy', $return))->assertSessionHasNoErrors();

        $this->assertNull(StockEntry::find($return->id));
        $this->assertSame('open', $loan->fresh()->status);
        $this->assertNull($loan->fresh()->return_stock_entry_id);
    }

    public function test_exit_can_be_edited_within_available_stock(): void
    {
        $this->entry(10);
        $exit = $this->exit(4);
        $item = $exit->items->first();
        $this->mock(NotificationService::class)->shouldReceive('send')->once()->withArgs(
            fn (array $payload) => $payload['model_action'] === 'update' && $payload['type'] === 'StockExit'
        );

        $this->put(route('stocks.exits.update', $exit), [
            'voucher_number' => 'V-2', 'recipient_name' => 'Maria', 'exited_at' => '2026-09-03',
            'items' => [$item->id => ['quantity' => 10]],
        ])->assertRedirect(route('stocks.exits.index'))->assertSessionHasNoErrors();

        $this->assertSame('V-2', $exit->fresh()->voucher_number);
        $this->assertEquals(10, $item->fresh()->quantity);

        $this->put(route('stocks.exits.update', $exit), [
            'voucher_number' => 'V-2', 'recipient_name' => 'Maria', 'exited_at' => '2026-09-03',
            'items' => [$item->id => ['quantity' => 11]],
        ])->assertSessionHasErrors();
        $this->assertEquals(10, $item->fresh()->quantity);
    }

    public function test_exit_deletion_restores_stock_and_is_audited(): void
    {
        $this->entry(10);
        $exit = $this->exit(4);
        $this->mock(NotificationService::class)->shouldReceive('send')->once()->withArgs(
            fn (array $payload) => $payload['model_action'] === 'destroy' && str_contains($payload['data'], 'V-1')
        );

        $this->delete(route('stocks.exits.destroy', $exit))->assertRedirect(route('stocks.exits.index'));

        $this->assertNull(StockExit::find($exit->id));
    }

    public function test_open_loan_can_be_edited_and_cancelled(): void
    {
        $loan = StockExit::create([
            'exit_type' => 'tool_loan', 'concept_id' => 1, 'voucher_number' => 'L-1', 'recipient_worker_id' => 1,
            'quantity' => 1, 'exited_at' => '2026-09-02', 'expected_return_at' => '2026-09-09',
            'status' => 'open', 'overdue_notified_at' => now(), 'created_by' => 1,
        ]);
        $this->mock(NotificationService::class)->shouldReceive('send')->twice();

        $this->put(route('stocks.exits.update', $loan), [
            'voucher_number' => 'L-2', 'recipient_name' => 'Juan Pérez', 'exited_at' => '2026-09-02', 'expected_return_at' => '2026-09-20',
        ])->assertSessionHasNoErrors();
        $loan->refresh();
        $this->assertSame('L-2', $loan->voucher_number);
        $this->assertSame('Juan Pérez', $loan->recipient_name);
        $this->assertNull($loan->recipient_worker_id);
        $this->assertSame('2026-09-20', $loan->expected_return_at->toDateString());
        $this->assertNull($loan->overdue_notified_at);

        $this->delete(route('stocks.exits.destroy', $loan))->assertSessionHasNoErrors();
        $this->assertNull(StockExit::find($loan->id));
    }
}
