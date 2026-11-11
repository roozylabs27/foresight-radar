<?php

namespace App\Support;

use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;

/**
 * The default driving force taxonomy of the scenario planning method (roadmap R0.3), which every
 * organisation starts from and may then customise (plan part B):
 * two environment levels and eleven categories, in display order. Earlier
 * category names are listed as legacy names so existing rows are renamed in
 * place and keep their forces and signals.
 *
 * Uses the query builder, not models, because the migration calls it too.
 */
final class DimensionTaxonomy
{
    /** @var array<int, array{code: string, name: string, environment: string, legacy: array<int, string>}> */
    public const CATEGORIES = [
        ['code' => 'POLITICAL', 'name' => 'Political', 'environment' => 'Macro', 'legacy' => ['Politic']],
        ['code' => 'ECONOMIC', 'name' => 'Economic', 'environment' => 'Macro', 'legacy' => ['Economy']],
        ['code' => 'SOCIAL', 'name' => 'Social', 'environment' => 'Macro', 'legacy' => []],
        ['code' => 'TECHNOLOGY', 'name' => 'Technology', 'environment' => 'Macro', 'legacy' => ['Technological']],
        ['code' => 'ECOLOGY', 'name' => 'Ecology', 'environment' => 'Macro', 'legacy' => ['Environmental']],
        // Pending the client's answer (question 5), "Regulation" is treated as Legislative.
        ['code' => 'LEGISLATIVE', 'name' => 'Legislative', 'environment' => 'Macro', 'legacy' => ['Regulation']],
        ['code' => 'CONSUMER', 'name' => 'Consumer', 'environment' => 'Mezzo', 'legacy' => ['Customer', 'Customers']],
        ['code' => 'SUPPLY_CHAIN', 'name' => 'Supply Chain', 'environment' => 'Mezzo', 'legacy' => ['Supplier', 'Suppliers']],
        ['code' => 'COMPETITOR', 'name' => 'Competitor', 'environment' => 'Mezzo', 'legacy' => ['Competitors', 'Existing competitors']],
        ['code' => 'NEW_ENTRANT', 'name' => 'New Entrant', 'environment' => 'Mezzo', 'legacy' => ['Entrance']],
        ['code' => 'SUBSTITUTE', 'name' => 'Substitute', 'environment' => 'Mezzo', 'legacy' => ['Product Substitute']],
    ];

    /**
     * Bring the current organisation's dimensions in line with CATEGORIES: match each category by
     * code, then by its current or legacy name, rename it in place, and create the missing ones.
     * With $createWhenEmpty false an empty table is left alone (fresh installs are seeded later).
     * Without a tenant context (console, tests) the first organisation is meant, as in BelongsToTenant.
     */
    public static function align(bool $createWhenEmpty = true): void
    {
        self::alignFor(app(TenantContext::class)->getTenantId() ?? 1, $createWhenEmpty);
    }

    /** A new organisation starts from the default taxonomy. */
    public static function seedFor(int $tenantId): void
    {
        self::alignFor($tenantId, true);
    }

    private static function alignFor(int $tenantId, bool $createWhenEmpty): void
    {
        // Migrations before 2026_11_11_000001 call this while dimensions are still shared.
        $owned = Schema::hasColumn('dimensions', 'tenant_id');
        $environments = fn () => DB::table('environments')->when($owned, fn ($q) => $q->where('tenant_id', $tenantId));
        $dimensions = fn () => DB::table('dimensions')->when($owned, fn ($q) => $q->where('tenant_id', $tenantId));
        $owner = $owned ? ['tenant_id' => $tenantId] : [];

        if (! $createWhenEmpty && ! $dimensions()->exists()) {
            return;
        }

        DB::transaction(function () use ($environments, $dimensions, $owner, $owned) {
            $now = now();
            $environmentOrder = array_values(array_unique(array_column(self::CATEGORIES, 'environment')));

            foreach (self::CATEGORIES as $position => $category) {
                $environmentId = $environments()->where('name', $category['environment'])->value('id')
                    ?? DB::table('environments')->insertGetId($owner + [
                        'uuid' => (string) Uuid::uuid4(), 'name' => $category['environment'], 'created_at' => $now, 'updated_at' => $now,
                    ] + ($owned ? ['sort_order' => array_search($category['environment'], $environmentOrder, true) + 1] : []));

                $id = $dimensions()->where('code', $category['code'])->value('id')
                    ?? $dimensions()->whereNull('code')->whereIn('name', [$category['name'], ...$category['legacy']])->orderBy('id')->value('id');

                $attributes = [
                    'code' => $category['code'],
                    'name' => $category['name'],
                    'environment_id' => $environmentId,
                    'sort_order' => $position + 1,
                    'updated_at' => $now,
                ];

                if ($id) {
                    DB::table('dimensions')->where('id', $id)->update($attributes);
                } else {
                    DB::table('dimensions')->insert($owner + $attributes + ['uuid' => (string) Uuid::uuid4(), 'created_at' => $now]);
                }
            }
        });
    }
}
