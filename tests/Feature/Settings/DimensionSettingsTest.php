<?php

namespace Tests\Feature\Settings;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Plan part B2: an organisation administrator (manage-organization) customises the organisation's
 * own dimensions and environments in Settings → Dimensi: rename, add, reorder, archive and colour.
 * A dimension or environment is deleted only while unused; codes never change once created.
 */
class DimensionSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'manage-organization', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'organization', 'description' => 'manage-organization']);
        DimensionTaxonomy::align();
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('manage-organization');
        $this->member = User::factory()->create();
    }

    private function dimension(string $code): Dimension
    {
        return Dimension::withoutGlobalScopes()->where('tenant_id', 1)->where('code', $code)->sole();
    }

    private function environment(string $name): Environment
    {
        return Environment::withoutGlobalScopes()->where('tenant_id', 1)->where('name', $name)->sole();
    }

    /** @return array<int, string> codes in display order */
    private function order(): array
    {
        return Dimension::withoutGlobalScopes()->where('tenant_id', 1)->ordered()->pluck('code')->all();
    }

    public function test_the_page_lists_the_environments_with_their_dimensions(): void
    {
        DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(), 'keyword' => 'Solid Battery', 'description' => 'Cells.', 'status' => 'APPROVED',
            'dimension_id' => $this->dimension('TECHNOLOGY')->id, 'created_by' => $this->admin->id, 'pic' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get(route('settings.dimensions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Dimensions')
                ->has('environments', 2)
                ->where('environments.0.name', 'Macro')
                ->has('environments.0.dimensions', 6)
                ->where('environments.0.dimensions.3.code', 'TECHNOLOGY')
                ->where('environments.0.dimensions.3.in_use', 1)
                ->where('environments.0.dimensions.3.archived', false)
                ->where('environments.1.dimensions.0.name', 'Consumer')
                ->has('colors'));

        $this->actingAs($this->member)->get(route('settings.dimensions.index'))->assertForbidden();
        $this->actingAs($this->member)->post(route('settings.dimensions.store'), ['name' => 'AI Policy', 'environment' => $this->environment('Macro')->uuid])->assertForbidden();
    }

    public function test_a_dimension_is_added_renamed_and_reordered(): void
    {
        $this->actingAs($this->admin)->post(route('settings.dimensions.store'), ['name' => 'AI Policy', 'environment' => $this->environment('Macro')->uuid, 'color' => 'teal'])
            ->assertSessionHasNoErrors();
        $added = $this->dimension('AI_POLICY');
        $this->assertSame(['AI Policy', 'teal', $this->environment('Macro')->id], [$added->name, $added->color, $added->environment_id]);
        // Last of its environment: the six Macro defaults, then the new one, then Mezzo.
        $this->assertSame('AI_POLICY', $this->order()[6]);

        $this->actingAs($this->admin)->post(route('settings.dimensions.store'), ['name' => ' technology ', 'environment' => $this->environment('Macro')->uuid])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post(route('settings.dimensions.store'), ['name' => 'Other', 'environment' => $this->environment('Macro')->uuid, 'color' => 'neon'])
            ->assertSessionHasErrors('color');

        $this->actingAs($this->admin)->put(route('settings.dimensions.update', $added), ['name' => 'Artificial Intelligence Policy', 'environment' => $this->environment('Mezzo')->uuid, 'color' => 'purple'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['AI_POLICY', 'Artificial Intelligence Policy', 'purple'], [$added->fresh()->code, $added->fresh()->name, $added->fresh()->color]);

        $before = $this->order();
        $this->actingAs($this->admin)->post(route('settings.dimensions.move', $this->dimension('ECONOMIC')), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertSame(['ECONOMIC', 'POLITICAL'], array_slice($this->order(), 0, 2));
        $this->actingAs($this->admin)->post(route('settings.dimensions.move', $this->dimension('ECONOMIC')), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertSame('ECONOMIC', $this->order()[0]);
        $this->assertCount(count($before), $this->order());
    }

    public function test_a_dimension_in_use_is_archived_not_deleted(): void
    {
        $technology = $this->dimension('TECHNOLOGY');
        DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(), 'keyword' => 'Solid Battery', 'description' => 'Cells.', 'status' => 'APPROVED',
            'dimension_id' => $technology->id, 'created_by' => $this->admin->id, 'pic' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->delete(route('settings.dimensions.destroy', $technology))->assertSessionHasErrors('dimension');
        $this->assertNotNull($technology->fresh());

        $this->actingAs($this->admin)->post(route('settings.dimensions.archive', $technology))->assertSessionHasNoErrors();
        $this->assertTrue($technology->fresh()->isArchived());
        $this->actingAs($this->admin)->delete(route('settings.dimensions.unarchive', $technology))->assertSessionHasNoErrors();
        $this->assertFalse($technology->fresh()->isArchived());

        $this->actingAs($this->admin)->delete(route('settings.dimensions.destroy', $this->dimension('SUBSTITUTE')))->assertSessionHasNoErrors();
        $this->assertSame(0, Dimension::withoutGlobalScopes()->where('tenant_id', 1)->where('code', 'SUBSTITUTE')->count());
    }

    public function test_environments_are_added_renamed_reordered_and_deleted_only_when_empty(): void
    {
        $this->actingAs($this->admin)->post(route('settings.environments.store'), ['name' => 'Micro'])->assertSessionHasNoErrors();
        $micro = $this->environment('Micro');
        $this->assertSame(['Macro', 'Mezzo', 'Micro'], Environment::withoutGlobalScopes()->where('tenant_id', 1)->ordered()->pluck('name')->all());

        $this->actingAs($this->admin)->post(route('settings.environments.store'), ['name' => 'macro'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->put(route('settings.environments.update', $micro), ['name' => 'Internal'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('settings.environments.move', $micro), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertSame(['Macro', 'Internal', 'Mezzo'], Environment::withoutGlobalScopes()->where('tenant_id', 1)->ordered()->pluck('name')->all());

        $this->actingAs($this->admin)->delete(route('settings.environments.destroy', $this->environment('Macro')))->assertSessionHasErrors('environment');
        $this->actingAs($this->admin)->delete(route('settings.environments.destroy', $micro))->assertSessionHasNoErrors();
        $this->assertSame(2, Environment::withoutGlobalScopes()->where('tenant_id', 1)->count());
    }

    public function test_another_organisation_cannot_touch_these_dimensions(): void
    {
        $other = Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active', 'max_users' => 15]);
        $outsider = User::factory()->create(['tenant_id' => $other->id]);
        $outsider->givePermissionTo('manage-organization');

        $this->actingAs($outsider)->put(route('settings.dimensions.update', $this->dimension('TECHNOLOGY')), ['name' => 'Hacked', 'environment' => $this->environment('Macro')->uuid])
            ->assertNotFound();
        $this->assertSame('Technology', $this->dimension('TECHNOLOGY')->name);

        // Its own page lists only its own copy.
        $this->actingAs($outsider)->get(route('settings.dimensions.index'))
            ->assertInertia(fn ($page) => $page->where('environments', fn ($rows) => collect($rows)->flatMap(fn ($env) => $env['dimensions'])->pluck('id')
                ->intersect(Dimension::withoutGlobalScopes()->where('tenant_id', 1)->pluck('uuid'))->isEmpty()));
    }
}
