<?php

namespace Tests\Feature\Taxonomy;

use App\Models\Tenant;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\TestCase;

/**
 * Audit 2026-11-11 (AUD-10/11): migration 2026_11_11_000001 is safe on prepared data. The owner is
 * the lowest organisation that is not deleted, name matching applies only to rows without a code, a
 * rerun changes nothing, and down() refuses before any change when merging would lose data.
 *
 * The tests call the migration's methods on data prepared in the test database; only the rerun test
 * calls up(), whose guards skip every schema change on an already migrated database.
 */
class DimensionMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();

        DimensionTaxonomy::align();
        $this->migration = require database_path('migrations/2026_11_11_000001_make_dimensions_tenant_owned.php');
    }

    /** An organisation inserted without the model event, so it starts with no taxonomy (as before the migration). */
    private function bareTenant(string $slug, array $attributes = []): int
    {
        return DB::table('tenants')->insertGetId($attributes + [
            'uuid' => (string) Uuid::uuid4(), 'name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function environment(int $tenantId, string $name): int
    {
        return DB::table('environments')->insertGetId(['uuid' => (string) Uuid::uuid4(), 'tenant_id' => $tenantId, 'name' => $name, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function dimension(int $tenantId, ?string $code, string $name, int $environmentId): int
    {
        return DB::table('dimensions')->insertGetId([
            'uuid' => (string) Uuid::uuid4(), 'tenant_id' => $tenantId, 'code' => $code, 'name' => $name,
            'environment_id' => $environmentId, 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_owner_is_the_lowest_organisation_that_is_not_deleted(): void
    {
        $this->assertSame(1, $this->migration->owner());

        $second = $this->bareTenant('second');
        DB::table('tenants')->where('id', 1)->update(['deleted_at' => now()]);

        $this->assertSame($second, $this->migration->owner());
    }

    public function test_names_are_matched_only_between_rows_without_a_code(): void
    {
        $macro = DB::table('environments')->where('tenant_id', 1)->where('name', 'Macro')->value('id');
        $this->dimension(1, null, 'Local Issues', $macro);
        $tenant = $this->bareTenant('prepared');
        $ownMacro = $this->environment($tenant, 'Macro');
        // The organisation already has a code-less "Technology" and a code-less "Local Issues".
        $codeless = $this->dimension($tenant, null, 'Technology', $ownMacro);
        $local = $this->dimension($tenant, null, 'Local Issues', $ownMacro);

        $this->migration->copyTaxonomy(1, $tenant);

        // TECHNOLOGY (with a code) got its own row instead of reusing the code-less one of the same name.
        $technology = DB::table('dimensions')->where('tenant_id', $tenant)->where('code', 'TECHNOLOGY')->sole();
        $this->assertNotSame($codeless, $technology->id);
        $this->assertNull(DB::table('dimensions')->where('id', $codeless)->value('code'));
        // The code-less "Local Issues" was reused.
        $this->assertSame(1, DB::table('dimensions')->where('tenant_id', $tenant)->where('name', 'Local Issues')->count());
        $this->assertSame($local, DB::table('dimensions')->where('tenant_id', $tenant)->where('name', 'Local Issues')->value('id'));
        $this->assertSame(1, DB::table('environments')->where('tenant_id', $tenant)->where('name', 'Macro')->count());
    }

    public function test_a_rerun_on_a_migrated_database_changes_nothing(): void
    {
        Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active', 'max_users' => 15]);
        $before = [DB::table('dimensions')->orderBy('id')->get()->toArray(), DB::table('environments')->orderBy('id')->get()->toArray()];

        $this->migration->up();

        $this->assertEquals($before, [DB::table('dimensions')->orderBy('id')->get()->toArray(), DB::table('environments')->orderBy('id')->get()->toArray()]);
        $key = collect(Schema::getForeignKeys('driving_forces'))->first(fn ($key) => $key['columns'] === ['dimension_id']);
        $this->assertSame('restrict', strtolower((string) $key['on_delete']));
    }

    public function test_rollback_is_allowed_only_while_every_organisation_has_the_owners_taxonomy(): void
    {
        $tenant = Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active', 'max_users' => 15]);
        $this->assertSame([], $this->migration->downBlockers());

        DB::table('dimensions')->where('tenant_id', $tenant->id)->where('code', 'TECHNOLOGY')->update(['name' => 'Teknologi Digital']);
        DB::table('dimensions')->where('tenant_id', $tenant->id)->where('code', 'LEGISLATIVE')->update(['archived_at' => now()]);
        $this->dimension($tenant->id, 'AI_POLICY', 'AI Policy', DB::table('environments')->where('tenant_id', $tenant->id)->value('id'));

        $blockers = $this->migration->downBlockers();
        $this->assertCount(1, $blockers);
        $this->assertStringContainsString("organisation #{$tenant->id}", $blockers[0]);
        foreach (['Teknologi Digital', 'AI Policy', 'LEGISLATIVE', 'missing'] as $part) {
            $this->assertStringContainsString($part, $blockers[0]);
        }
    }

    public function test_rollback_stops_before_any_change_when_it_would_lose_data(): void
    {
        $tenant = Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active', 'max_users' => 15]);
        DB::table('dimensions')->where('tenant_id', $tenant->id)->where('code', 'TECHNOLOGY')->update(['name' => 'Teknologi Digital']);
        $before = DB::table('dimensions')->orderBy('id')->get()->toArray();

        try {
            $this->migration->down();
            $this->fail('down() should refuse');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Restore the pre-deploy backup', $e->getMessage());
        }

        $this->assertEquals($before, DB::table('dimensions')->orderBy('id')->get()->toArray());
        $this->assertTrue(Schema::hasColumn('dimensions', 'tenant_id'));
    }
}
