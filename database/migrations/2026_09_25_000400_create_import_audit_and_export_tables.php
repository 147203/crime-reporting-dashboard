<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users', indexName: 'import_batches_user_id_foreign')->restrictOnDelete();
            $table->string('file_name', 150);
            $table->string('stored_path', 255)->nullable();
            $table->string('status', 12)->default('dry_run');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_accepted')->default(0);
            $table->unsignedInteger('rows_rejected')->default(0);
            $table->unsignedInteger('rows_flagged_duplicate')->default(0);
            // Row number, column and rule ID only: never the rejected cell values, which may hold the PII that V6 refused.
            $table->json('error_report')->nullable();
            $table->timestamps();

            $table->index('created_at', 'import_batches_created_at_index');
        });

        // Append-only: the model refuses updates and deletes. Metadata only, never the content of sensitive fields.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users', indexName: 'audit_logs_user_id_foreign')->restrictOnDelete();
            $table->string('action', 60);
            $table->string('entity_type', 60)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at', 'audit_logs_created_at_index');
            $table->index(['user_id', 'created_at'], 'audit_logs_user_created_index');
            $table->index('action', 'audit_logs_action_index');
        });

        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users', indexName: 'report_exports_user_id_foreign')->restrictOnDelete();
            $table->string('type', 10);
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at', 'report_exports_created_at_index');
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->foreign('import_batch_id', 'incidents_import_batch_id_foreign')->references('id')->on('import_batches')->nullOnDelete();
        });

        // Append-only enforced in the database as well, so query-builder or console writes cannot rewrite history.
        DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only'");
        DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only'");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropForeign('incidents_import_batch_id_foreign');
        });
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('import_batches');
    }
};
