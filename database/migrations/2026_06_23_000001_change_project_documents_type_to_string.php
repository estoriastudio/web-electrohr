<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requiere un índice en project_id para la FK antes de poder eliminar
        // el índice único compuesto (project_id, document_type).
        Schema::table('project_documents', function (Blueprint $table) {
            $table->index('project_id', 'project_documents_project_id_idx');
        });

        Schema::table('project_documents', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'document_type']);
        });

        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE project_documents MODIFY document_type VARCHAR(100) NOT NULL'
        );

        Schema::table('project_documents', function (Blueprint $table) {
            $table->unique(['project_id', 'document_type']);
            $table->dropIndex('project_documents_project_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->index('project_id', 'project_documents_project_id_idx');
        });

        Schema::table('project_documents', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'document_type']);
        });

        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE project_documents MODIFY document_type ENUM('licitacion','contratacion','entrega_recepcion') NOT NULL"
        );

        Schema::table('project_documents', function (Blueprint $table) {
            $table->unique(['project_id', 'document_type']);
            $table->dropIndex('project_documents_project_id_idx');
        });
    }
};
