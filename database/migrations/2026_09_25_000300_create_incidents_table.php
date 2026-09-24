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
        // Location is a reference hierarchy plus free text only; there is deliberately no coordinate or spatial column (D-02).
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 20)->nullable()->unique('incidents_reference_no_unique');
            $table->foreignId('category_id')->constrained('crime_categories', indexName: 'incidents_category_id_foreign')->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->dateTime('reported_at');
            $table->foreignId('sub_county_id')->constrained('sub_counties', indexName: 'incidents_sub_county_id_foreign')->restrictOnDelete();
            $table->foreignId('ward_id');
            $table->foreignId('station_id')->constrained('stations', indexName: 'incidents_station_id_foreign')->restrictOnDelete();
            $table->string('location_description', 150)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('reported');
            $table->dateTime('closed_at')->nullable();
            $table->string('duplicate_status', 12)->default('none');
            $table->foreignId('duplicate_of_id')->nullable()->constrained('incidents', indexName: 'incidents_duplicate_of_id_foreign')->nullOnDelete();
            // Everything in this project is sample data (D-07); a record is labelled real only by an explicit, logged decision.
            $table->boolean('is_synthetic')->default(true);
            $table->foreignId('import_batch_id')->nullable()->index('incidents_import_batch_id_index');
            // Users are deactivated, never deleted (FR-03), so provenance is kept with restrict.
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'incidents_created_by_foreign')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'incidents_updated_by_foreign')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_at', 'incidents_occurred_at_index');
            $table->index(['category_id', 'occurred_at'], 'incidents_category_occurred_index');
            $table->index(['ward_id', 'occurred_at'], 'incidents_ward_occurred_index');
            $table->index(['sub_county_id', 'occurred_at'], 'incidents_sub_county_occurred_index');
            $table->index(['station_id', 'occurred_at'], 'incidents_station_occurred_index');
            $table->index('status', 'incidents_status_index');
            $table->index('duplicate_status', 'incidents_duplicate_status_index');

            $table->foreign(['ward_id', 'sub_county_id'], 'incidents_ward_sub_county_foreign')
                ->references(['id', 'sub_county_id'])->on('wards')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_status_check CHECK (status IN ('reported', 'under_investigation', 'closed'))");
        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_duplicate_status_check CHECK (duplicate_status IN ('none', 'suspected', 'confirmed', 'dismissed'))");
        DB::statement("ALTER TABLE incidents ADD CONSTRAINT incidents_closed_at_check CHECK ((status = 'closed') = (closed_at IS NOT NULL))");
        DB::statement('ALTER TABLE incidents ADD CONSTRAINT incidents_reported_after_occurred_check CHECK (reported_at >= occurred_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
