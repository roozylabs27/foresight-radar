<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;

/**
 * Plan part B1: environments and dimensions belong to one organisation. The existing rows go to the
 * owner (the lowest organisation that is not deleted); every other organisation gets its own copy,
 * and its driving forces, signals and force candidates are moved to the copy (matched by code, or by
 * name for rows without a code). Codes become unique per organisation, dimensions can be archived and
 * carry a colour key, and a dimension that still has driving forces can no longer be deleted (it
 * used to delete them).
 *
 * Every step is guarded so a rerun after a failed attempt is safe (MySQL does not roll back schema
 * changes). down() refuses to run when merging the organisations back would lose data.
 */
return new class extends Migration
{
    private const DATA_TABLES = ['driving_forces', 'signals', 'force_candidates'];

    public function up(): void
    {
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

        $owner = $this->owner();
        if ($owner !== null) {
            DB::table('environments')->whereNull('tenant_id')->update(['tenant_id' => $owner]);
            DB::table('dimensions')->whereNull('tenant_id')->update(['tenant_id' => $owner]);
            foreach (DB::table('environments')->where('tenant_id', $owner)->orderBy('id')->pluck('id')->values() as $position => $id) {
                DB::table('environments')->where('id', $id)->update(['sort_order' => $position + 1]);
            }
            // Deleted organisations get a copy too, so none of their data keeps pointing across.
            foreach (DB::table('tenants')->where('id', '!=', $owner)->orderBy('id')->pluck('id') as $tenantId) {
                $this->copyTaxonomy($owner, (int) $tenantId);
            }
        }

        $this->forceForeignKey('restrict');
    }

    /** The organisation that keeps the existing rows: the lowest one that is not deleted. */
    public function owner(): ?int
    {
        $id = DB::table('tenants')->whereNull('deleted_at')->min('id') ?? DB::table('tenants')->min('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Give $toTenant its own copy of $fromTenant's environments and dimensions, reusing what it
     * already has (same code, or same name where both have no code), and move $toTenant's data that
     * still points at $fromTenant's dimensions to its own. Safe to run twice.
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

    /**
     * Back to one shared taxonomy. Only safe while every organisation still has exactly the owner's
     * taxonomy: then each copy has an identical row to merge into and nothing is lost. Otherwise (a
     * renamed, added, archived or moved dimension, or another environment) the rollback stops before
     * changing anything; restore the backup taken before the deploy instead.
     */
    public function down(): void
    {
        $blockers = $this->downBlockers();
        if ($blockers !== []) {
            throw new RuntimeException(
                "Rollback stopped before any change: merging the organisations' dimensions would lose data.".PHP_EOL
                .'- '.implode(PHP_EOL.'- ', $blockers).PHP_EOL
                .'Restore the pre-deploy backup instead.'
            );
        }

        $owner = $this->owner();
        if ($owner !== null) {
            DB::transaction(function () use ($owner) {
                foreach (DB::table('dimensions')->where('tenant_id', '!=', $owner)->get() as $copy) {
                    $shared = DB::table('dimensions')->where('tenant_id', $owner)
                        ->where(fn ($q) => $copy->code !== null ? $q->where('code', $copy->code) : $q->whereNull('code')->where('name', $copy->name))
                        ->value('id');
                    foreach (self::DATA_TABLES as $table) {
                        DB::table($table)->where('dimension_id', $copy->id)->update(['dimension_id' => $shared]);
                    }
                    DB::table('dimensions')->where('id', $copy->id)->delete();
                }
                DB::table('environments')->where('tenant_id', '!=', $owner)->delete();
            });
        }

        $this->forceForeignKey('cascade');
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

    /**
     * Why down() would lose data: for each organisation whose taxonomy differs from the owner's, the
     * lines found only there and the lines missing there.
     *
     * @return list<string>
     */
    public function downBlockers(): array
    {
        $owner = $this->owner();
        if ($owner === null || ! Schema::hasColumn('dimensions', 'tenant_id')) {
            return [];
        }

        $expected = $this->taxonomyOf($owner);
        $tenants = DB::table('dimensions')->where('tenant_id', '!=', $owner)->distinct()->pluck('tenant_id')
            ->merge(DB::table('environments')->where('tenant_id', '!=', $owner)->distinct()->pluck('tenant_id'))
            ->unique()->sort()->values();

        $blockers = [];
        foreach ($tenants as $tenantId) {
            $actual = $this->taxonomyOf((int) $tenantId);
            $parts = array_filter([
                ($extra = array_diff($actual, $expected)) ? 'only here ['.implode('; ', $extra).']' : null,
                ($missing = array_diff($expected, $actual)) ? 'missing ['.implode('; ', $missing).']' : null,
            ]);
            if ($parts !== []) {
                $blockers[] = "organisation #{$tenantId}: ".implode(', ', $parts);
            }
        }

        return $blockers;
    }

    /**
     * An organisation's taxonomy as comparable lines: each environment, and each dimension with its
     * code, name, environment and archive state.
     *
     * @return list<string>
     */
    private function taxonomyOf(int $tenantId): array
    {
        $environments = DB::table('environments')->where('tenant_id', $tenantId)->pluck('name', 'id');
        $lines = $environments->map(fn ($name) => "environment {$name}")->values()->all();
        foreach (DB::table('dimensions')->where('tenant_id', $tenantId)->get() as $dimension) {
            $lines[] = 'dimension '.($dimension->code ?? '(no code)').": {$dimension->name} in "
                .($environments[$dimension->environment_id] ?? '?').($dimension->archived_at ? ' (archived)' : '');
        }
        sort($lines);

        return $lines;
    }

    /** Give driving_forces.dimension_id this delete rule; nothing to do when it already has it (rerun). */
    private function forceForeignKey(string $onDelete): void
    {
        $current = collect(Schema::getForeignKeys('driving_forces'))->first(fn ($key) => $key['columns'] === ['dimension_id']);
        if ($current !== null && strtolower((string) $current['on_delete']) === $onDelete) {
            return;
        }

        Schema::table('driving_forces', function (Blueprint $table) use ($current, $onDelete) {
            if ($current !== null) {
                $table->dropForeign($current['name'] ?: ['dimension_id']);
            }
            $table->foreign('dimension_id')->references('id')->on('dimensions')->onDelete($onDelete);
        });
    }
};
