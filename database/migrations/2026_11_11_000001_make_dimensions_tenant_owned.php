<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;

/**
 * Plan part B1: environments and dimensions belong to one organisation. The existing rows go to the
 * first organisation; every other organisation gets its own copy, and its driving forces, signals
 * and force candidates are moved to the copy (matched by code, or by name for rows without a code).
 * Codes become unique per organisation, dimensions can be archived and carry a colour key, and a
 * dimension that still has driving forces can no longer be deleted (it used to delete them).
 */
return new class extends Migration
{
    private const DATA_TABLES = ['driving_forces', 'signals', 'force_candidates'];

    public function up(): void
    {
        // Guards make a rerun after a failed attempt safe (MySQL does not roll back schema changes).
        if (! Schema::hasColumn('environments', 'tenant_id')) {
            Schema::table('environments', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0)->after('name');
            });
        }
        if (! Schema::hasColumn('dimensions', 'tenant_id')) {
            Schema::table('dimensions', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
                $table->timestamp('archived_at')->nullable()->after('sort_order');
                $table->string('color', 20)->nullable()->after('archived_at');
            });
        }

        // Codes become unique per organisation before the copies are made, or the copies collide.
        if (Schema::hasIndex('dimensions', 'dimensions_code_unique')) {
            Schema::table('dimensions', fn (Blueprint $table) => $table->dropUnique(['code']));
        }
        if (! Schema::hasIndex('dimensions', 'dimensions_tenant_id_code_unique')) {
            Schema::table('dimensions', function (Blueprint $table) {
                $table->unique(['tenant_id', 'code']);
                $table->index(['tenant_id', 'sort_order']);
            });
        }

        $owner = DB::table('tenants')->min('id');
        if ($owner !== null) {
            DB::table('environments')->whereNull('tenant_id')->update(['tenant_id' => $owner]);
            DB::table('dimensions')->whereNull('tenant_id')->update(['tenant_id' => $owner]);
            foreach (DB::table('environments')->where('tenant_id', $owner)->orderBy('id')->pluck('id')->values() as $position => $id) {
                DB::table('environments')->where('id', $id)->update(['sort_order' => $position + 1]);
            }
            foreach (DB::table('tenants')->where('id', '!=', $owner)->orderBy('id')->pluck('id') as $tenantId) {
                $this->copyTaxonomy((int) $owner, (int) $tenantId);
            }
        }

        Schema::table('driving_forces', function (Blueprint $table) {
            $table->dropForeign(['dimension_id']);
            $table->foreign('dimension_id')->references('id')->on('dimensions')->restrictOnDelete();
        });
    }

    /**
     * Give $toTenant its own copy of $fromTenant's environments and dimensions, reusing what it
     * already has (same code, or same name without a code), and move $toTenant's data that still
     * points at $fromTenant's dimensions to its own. Safe to run twice.
     */
    public function copyTaxonomy(int $fromTenant, int $toTenant): void
    {
        DB::transaction(function () use ($fromTenant, $toTenant) {
            $now = now();

            $environments = [];
            foreach (DB::table('environments')->where('tenant_id', $fromTenant)->orderBy('id')->get() as $environment) {
                $environments[$environment->id] = DB::table('environments')->where('tenant_id', $toTenant)->where('name', $environment->name)->value('id')
                    ?? DB::table('environments')->insertGetId([
                        'uuid' => (string) Uuid::uuid4(), 'tenant_id' => $toTenant, 'name' => $environment->name,
                        'sort_order' => $environment->sort_order, 'created_at' => $now, 'updated_at' => $now,
                    ]);
            }

            foreach (DB::table('dimensions')->where('tenant_id', $fromTenant)->orderBy('id')->get() as $dimension) {
                $target = DB::table('dimensions')->where('tenant_id', $toTenant)
                    ->where(fn ($q) => $dimension->code !== null ? $q->where('code', $dimension->code) : $q->whereNull('code')->where('name', $dimension->name))
                    ->value('id')
                    ?? DB::table('dimensions')->insertGetId([
                        'uuid' => (string) Uuid::uuid4(), 'tenant_id' => $toTenant, 'code' => $dimension->code, 'name' => $dimension->name,
                        'environment_id' => $environments[$dimension->environment_id] ?? $dimension->environment_id, 'sort_order' => $dimension->sort_order,
                        'archived_at' => $dimension->archived_at, 'color' => $dimension->color, 'created_at' => $now, 'updated_at' => $now,
                    ]);

                foreach (self::DATA_TABLES as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->where('tenant_id', $toTenant)->where('dimension_id', $dimension->id)->update(['dimension_id' => $target]);
                    }
                }
            }
        });
    }

    /** Back to one shared taxonomy: data moves to the first organisation's rows (by code or name) and the copies go. */
    public function down(): void
    {
        $owner = DB::table('tenants')->min('id');

        Schema::table('driving_forces', function (Blueprint $table) {
            $table->dropForeign(['dimension_id']);
            $table->foreign('dimension_id')->references('id')->on('dimensions')->cascadeOnDelete();
        });

        if ($owner !== null) {
            foreach (DB::table('dimensions')->where('tenant_id', '!=', $owner)->get() as $copy) {
                $shared = DB::table('dimensions')->where('tenant_id', $owner)
                    ->where(fn ($q) => $copy->code !== null ? $q->where('code', $copy->code) : $q->where('name', $copy->name))
                    ->value('id');
                if ($shared === null) {
                    continue;
                }
                foreach (self::DATA_TABLES as $table) {
                    DB::table($table)->where('dimension_id', $copy->id)->update(['dimension_id' => $shared]);
                }
                DB::table('dimensions')->where('id', $copy->id)->delete();
            }
            DB::table('environments')->where('tenant_id', '!=', $owner)->whereNotExists(
                fn ($q) => $q->from('dimensions')->whereColumn('dimensions.environment_id', 'environments.id')
            )->delete();
        }

        Schema::table('dimensions', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->dropIndex(['tenant_id', 'sort_order']);
            $table->unique(['code']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['archived_at', 'color']);
        });
        Schema::table('environments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('sort_order');
        });
    }
};
