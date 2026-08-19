<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->foreignId('position_category_id')
                ->nullable()
                ->after('hire_date')
                ->constrained('position_categories')
                ->nullOnDelete();
            $table->string('payment_type')->default('salaried')->after('weekly_salary');
            $table->index(['payment_type', 'position_category_id']);
        });

        Schema::table('worker_terminations', function (Blueprint $table) {
            $table->foreignId('position_category_id')
                ->nullable()
                ->after('project_work_id')
                ->constrained('position_categories')
                ->nullOnDelete();
        });

        $this->migrateJobTitles('workers');
        $this->migrateJobTitles('worker_terminations');

        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn('job_title');
        });

        Schema::table('worker_terminations', function (Blueprint $table) {
            $table->dropColumn('job_title');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('hire_date');
        });

        Schema::table('worker_terminations', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('project_work_id');
        });

        $this->restoreJobTitles('workers');
        $this->restoreJobTitles('worker_terminations');

        Schema::table('worker_terminations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_category_id');
        });

        Schema::table('workers', function (Blueprint $table) {
            $table->dropIndex(['payment_type', 'position_category_id']);
            $table->dropColumn('payment_type');
            $table->dropConstrainedForeignId('position_category_id');
        });
    }

    private function migrateJobTitles(string $table): void
    {
        DB::table($table)
            ->select(['id', 'job_title'])
            ->whereNotNull('job_title')
            ->orderBy('id')
            ->each(function (object $record) use ($table): void {
                $name = $this->normalizeName($record->job_title);

                if ($name === '') {
                    return;
                }

                $categoryId = DB::table('position_categories')
                    ->where('name', $name)
                    ->value('id');

                if (! $categoryId) {
                    $categoryId = DB::table('position_categories')->insertGetId([
                        'name' => $name,
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], 'id');
                }

                DB::table($table)
                    ->where('id', $record->id)
                    ->update(['position_category_id' => $categoryId]);
            });
    }

    private function restoreJobTitles(string $table): void
    {
        DB::table($table)
            ->join('position_categories', 'position_categories.id', '=', "{$table}.position_category_id")
            ->update(["{$table}.job_title" => DB::raw('position_categories.name')]);
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/\s+/', ' ', trim($name)) ?? '';
    }
};