<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Covering index for the analytics aggregates: a range on occurred_at plus every column the dashboard groups or
     * filters by, so each aggregate reads the index only. Without it MariaDB walked the (category_id, occurred_at) index
     * end to end to avoid a sort; measured at 105,000 incidents the A1 to A10 set went from 3,525 ms to 390 ms.
     */
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->index(
                ['occurred_at', 'duplicate_status', 'deleted_at', 'category_id', 'sub_county_id', 'ward_id', 'station_id', 'status', 'reported_at', 'closed_at'],
                'incidents_analytics_covering_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex('incidents_analytics_covering_index');
        });
    }
};
