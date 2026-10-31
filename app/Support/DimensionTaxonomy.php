<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * The driving force taxonomy of the scenario planning method (roadmap R0.3):
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
     * Bring the dimensions table in line with CATEGORIES: match each category by code, then by
     * its current or legacy name, rename it in place, and create the missing ones. With
     * $createWhenEmpty false an empty table is left alone (fresh installs are seeded later).
     */
    public static function align(bool $createWhenEmpty = true): void
    {
        if (! $createWhenEmpty && ! DB::table('dimensions')->exists()) {
            return;
        }

        DB::transaction(function () {
            $now = now();

            foreach (self::CATEGORIES as $position => $category) {
                $environmentId = DB::table('environments')->where('name', $category['environment'])->value('id')
                    ?? DB::table('environments')->insertGetId([
                        'uuid' => (string) Uuid::uuid4(), 'name' => $category['environment'], 'created_at' => $now, 'updated_at' => $now,
                    ]);

                $id = DB::table('dimensions')->where('code', $category['code'])->value('id')
                    ?? DB::table('dimensions')->whereNull('code')->whereIn('name', [$category['name'], ...$category['legacy']])->orderBy('id')->value('id');

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
                    DB::table('dimensions')->insert($attributes + ['uuid' => (string) Uuid::uuid4(), 'created_at' => $now]);
                }
            }
        });
    }
}
