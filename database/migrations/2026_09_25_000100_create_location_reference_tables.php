<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_counties', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique('sub_counties_name_unique');
            $table->timestamps();
        });

        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_county_id')->constrained('sub_counties', indexName: 'wards_sub_county_id_foreign')->restrictOnDelete();
            $table->string('name', 80);
            $table->timestamps();

            $table->unique(['sub_county_id', 'name'], 'wards_sub_county_id_name_unique');
            // Target of the incidents composite key, so an incident's ward always belongs to its sub-county (V4).
            $table->unique(['id', 'sub_county_id'], 'wards_id_sub_county_id_unique');
        });

        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_county_id')->constrained('sub_counties', indexName: 'stations_sub_county_id_foreign')->restrictOnDelete();
            $table->string('name', 120)->unique('stations_name_unique');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('crime_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique('crime_categories_name_unique');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crime_categories');
        Schema::dropIfExists('stations');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('sub_counties');
    }
};
