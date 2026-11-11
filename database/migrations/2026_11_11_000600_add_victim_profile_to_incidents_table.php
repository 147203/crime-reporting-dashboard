<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Victim profile (D-53): descriptive, non-identifying attributes only. No names, contacts, ID numbers or addresses of
 * victims are ever stored. Every new column has a default, so existing rows read as "not recorded".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->string('victim_type', 20)->default('not_recorded')->after('description');
            $table->unsignedTinyInteger('victim_count')->nullable()->after('victim_type');
            $table->string('victim_sex', 12)->default('not_recorded')->after('victim_count');
            $table->unsignedTinyInteger('victim_age')->nullable()->after('victim_sex');
            $table->string('victim_age_band', 12)->default('not_recorded')->after('victim_age');
            $table->string('victim_injury', 12)->default('not_recorded')->after('victim_age_band');
            $table->string('offender_relationship', 20)->default('not_known')->after('victim_injury');

            $table->index('victim_sex', 'incidents_victim_sex_index');
            $table->index('victim_age_band', 'incidents_victim_age_band_index');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropIndex('incidents_victim_sex_index');
            $table->dropIndex('incidents_victim_age_band_index');
            $table->dropColumn(['victim_type', 'victim_count', 'victim_sex', 'victim_age', 'victim_age_band', 'victim_injury', 'offender_relationship']);
        });
    }
};
