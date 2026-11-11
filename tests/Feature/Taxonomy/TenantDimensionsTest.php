<?php

namespace Tests\Feature\Taxonomy;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\Signal;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\DimensionTaxonomy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Plan part B1: dimensions and environments belong to one organisation. Each organisation starts
 * from the default taxonomy, sees and uses only its own dimensions, and cannot attach new forces
 * to an archived one. Existing data was copied per organisation by the migration.
 */
class TenantDimensionsTest extends TestCase
{
    use RefreshDatabase;

    private User $analystA;

    private User $analystB;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-driving-force', 'create-driving-force', 'edit-driving-force', 'view-prioritizing'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'driving-force', 'description' => $name]);
        }
        DimensionTaxonomy::align();
        $this->analystA = User::factory()->create();
        $this->analystA->givePermissionTo(['view-driving-force', 'create-driving-force', 'edit-driving-force', 'view-prioritizing']);

        $this->tenantB = Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active', 'max_users' => 15]);
        $this->analystB = User::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->analystB->givePermissionTo(['view-driving-force', 'create-driving-force', 'edit-driving-force', 'view-prioritizing']);
    }

    /** @return array<string, int> code => id of a tenant's dimensions */
    private function dimensionsOf(int $tenantId): array
    {
        return DB::table('dimensions')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
    }

    private function forcePayload(int $dimensionId, User $pic): array
    {
        return ['keyword' => 'Battery Recycling Mandate', 'description' => 'Recycling rules for EV batteries.', 'dimension_id' => $dimensionId, 'pic_id' => $pic->id];
    }

    public function test_a_new_organisation_starts_from_its_own_default_taxonomy(): void
    {
        $a = $this->dimensionsOf(1);
        $b = $this->dimensionsOf($this->tenantB->id);

        $this->assertCount(11, $b);
        $this->assertSame(array_keys($a), array_keys($b));
        $this->assertSame([], array_intersect($a, $b));
        $this->assertSame(2, DB::table('environments')->where('tenant_id', $this->tenantB->id)->count());

        $this->actingAs($this->analystB)->get('/visualization/prioritizing')
            ->assertInertia(fn ($page) => $page->where('dimensions', fn ($rows) => collect($rows)->pluck('value')->sort()->values()->all() === collect($b)->sort()->values()->all()));
    }

    public function test_another_organisations_dimension_cannot_be_used(): void
    {
        $foreign = $this->dimensionsOf(1)['TECHNOLOGY'];

        $this->actingAs($this->analystB)->post(route('driving-force.create'), $this->forcePayload($foreign, $this->analystB))
            ->assertSessionHasErrors('dimension_id');

        $own = $this->dimensionsOf($this->tenantB->id)['TECHNOLOGY'];
        $this->actingAs($this->analystB)->post(route('driving-force.create'), $this->forcePayload($own, $this->analystB))
            ->assertSessionHasNoErrors();
        $this->assertSame($own, DrivingForce::withoutGlobalScopes()->where('tenant_id', $this->tenantB->id)->sole()->dimension_id);
    }

    public function test_an_archived_dimension_takes_no_new_forces_but_keeps_its_old_ones(): void
    {
        $technology = Dimension::where('tenant_id', 1)->where('code', 'TECHNOLOGY')->sole();
        $old = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(), 'keyword' => 'Solid Battery', 'description' => 'Solid state cells.', 'status' => 'APPROVED',
            'dimension_id' => $technology->id, 'created_by' => $this->analystA->id, 'pic' => $this->analystA->id,
        ]);
        $technology->update(['archived_at' => now()]);

        $this->actingAs($this->analystA)->post(route('driving-force.create'), $this->forcePayload($technology->id, $this->analystA))
            ->assertSessionHasErrors('dimension_id');

        // Editing a force that already sits on the archived dimension may keep it there.
        $this->actingAs($this->analystA)->put(route('driving-force.update', $old->uuid), array_merge($this->forcePayload($technology->id, $this->analystA), ['keyword' => 'Solid State Battery']))
            ->assertSessionHasNoErrors();
        $this->assertSame([$technology->id, 'Solid State Battery'], [$old->fresh()->dimension_id, $old->fresh()->keyword]);

        // The force table offers it for filtering, flagged, so the form can leave it out.
        $this->actingAs($this->analystA)->get('/driving-force')
            ->assertInertia(fn ($page) => $page->where('dimensions', fn ($rows) => collect($rows)->firstWhere('value', $technology->id)['archived'] === true));
    }

    public function test_a_dimension_in_use_cannot_be_deleted_from_the_database(): void
    {
        $technology = Dimension::where('tenant_id', 1)->where('code', 'TECHNOLOGY')->sole();
        DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(), 'keyword' => 'Solid Battery', 'description' => 'Solid state cells.', 'status' => 'APPROVED',
            'dimension_id' => $technology->id, 'created_by' => $this->analystA->id, 'pic' => $this->analystA->id,
        ]);

        $this->expectException(QueryException::class);
        DB::table('dimensions')->where('id', $technology->id)->delete();
    }

    /** The migration step: an organisation's data that still points at another organisation's dimensions is moved to its own copy, matched by code. */
    public function test_the_migration_copies_the_taxonomy_and_remaps_each_organisations_data(): void
    {
        $sharedTechnology = $this->dimensionsOf(1)['TECHNOLOGY'];
        $custom = Dimension::create(['uuid' => (string) Uuid::uuid4(), 'code' => 'AI_POLICY', 'name' => 'AI Policy', 'environment_id' => Environment::where('tenant_id', 1)->where('name', 'Macro')->value('id'), 'sort_order' => 12]);

        app(TenantContext::class)->setTenantId($this->tenantB->id);
        $force = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(), 'keyword' => 'Robotaxi Pilot', 'description' => 'Pilot.', 'status' => 'APPROVED',
            'dimension_id' => $sharedTechnology, 'created_by' => $this->analystB->id, 'pic' => $this->analystB->id,
        ]);
        $source = Source::create(['uuid' => (string) Uuid::uuid4(), 'title' => 'Doc', 'raw_content' => 'x', 'content_hash' => hash('sha256', 'x'), 'created_by' => $this->analystB->id]);
        $signal = Signal::create(['uuid' => (string) Uuid::uuid4(), 'source_id' => $source->id, 'dimension_id' => $custom->id, 'title' => 'AI rule', 'summary' => 'AI rule.', 'evidence_quote' => 'x', 'review_status' => 'PENDING']);
        app(TenantContext::class)->setTenantId(null);

        $migration = require database_path('migrations/2026_11_11_000001_make_dimensions_tenant_owned.php');
        $migration->copyTaxonomy(1, $this->tenantB->id);
        $migration->copyTaxonomy(1, $this->tenantB->id);

        $b = $this->dimensionsOf($this->tenantB->id);
        $this->assertCount(12, $b);
        $this->assertSame($b['TECHNOLOGY'], DB::table('driving_forces')->where('id', $force->id)->value('dimension_id'));
        $this->assertSame($b['AI_POLICY'], DB::table('signals')->where('id', $signal->id)->value('dimension_id'));
        $this->assertSame('AI Policy', DB::table('dimensions')->where('id', $b['AI_POLICY'])->value('name'));
        $this->assertSame(12, count($this->dimensionsOf(1)));
    }
}
