<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Least privilege for any pre-existing row: an officer with no station can neither see nor create incidents.
            $table->string('role', 20)->default('officer')->after('password');
            $table->foreignId('station_id')->nullable()->after('role')->constrained('stations', indexName: 'users_station_id_foreign')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('station_id');
            $table->unsignedSmallInteger('failed_attempts')->default(0)->after('is_active');
            $table->timestamp('locked_until')->nullable()->after('failed_attempts');
            $table->boolean('must_change_password')->default(true)->after('locked_until');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');

            $table->index('role', 'users_role_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_station_id_foreign');
            $table->dropIndex('users_role_index');
            $table->dropColumn(['role', 'station_id', 'is_active', 'failed_attempts', 'locked_until', 'must_change_password', 'last_login_at']);
        });
    }
};
