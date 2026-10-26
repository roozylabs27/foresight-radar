<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure Default Tenant exists before associating data
        $now = now();
        $defaultTenantId = DB::table('tenants')->where('id', 1)->value('id');
        if (! $defaultTenantId) {
            $defaultTenantId = DB::table('tenants')->insertGetId([
                'id' => 1,
                'uuid' => (string) Str::uuid(),
                'name' => 'Default Organization',
                'slug' => 'default-org',
                'status' => 'active',
                'plan' => 'enterprise',
                'max_users' => 100,
                'storage_quota_mb' => 51200,
                'llm_monthly_token_quota' => 50000000,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2. Add tenant_id to users
        if (! Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            });
        }

        // 3. Add tenant_id to domain tables with accurate existing column indexes
        $tables = [
            'sources' => ['created_at'],
            'signals' => ['dimension_id', 'review_status'],
            'driving_forces' => ['dimension_id', 'status'],
            'driving_force_ratings' => ['driving_force_id'],
            'driving_force_rating_histories' => ['driving_force_id'],
            'cross_impacts' => ['driving_force_a_id', 'driving_force_b_id'],
            'scenarios' => ['status'],
            'scenario_quadrants' => ['scenario_id'],
            'user_llm_settings' => ['user_id', 'provider'],
        ];

        foreach ($tables as $tableName => $indexes) {
            if (Schema::hasTable($tableName)) {
                if (! Schema::hasColumn($tableName, 'tenant_id')) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                    });
                }

                // Add composite indexes safely if columns exist
                foreach ($indexes as $indexCol) {
                    if (Schema::hasColumn($tableName, $indexCol)) {
                        $indexName = "{$tableName}_tenant_id_{$indexCol}_index";
                        // Safely add index via blueprint
                        try {
                            Schema::table($tableName, function (Blueprint $table) use ($indexCol, $indexName) {
                                $table->index(['tenant_id', $indexCol], $indexName);
                            });
                        } catch (Throwable $e) {
                            // Index might already exist
                        }
                    }
                }
            }
        }

        // 4. Backfill existing data to default tenant
        DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);

        foreach (array_keys($tables) as $tableName) {
            if (Schema::hasTable($tableName)) {
                DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'sources',
            'signals',
            'driving_forces',
            'driving_force_ratings',
            'driving_force_rating_histories',
            'cross_impacts',
            'scenarios',
            'scenario_quadrants',
            'user_llm_settings',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                    $table->dropColumn('tenant_id');
                });
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
