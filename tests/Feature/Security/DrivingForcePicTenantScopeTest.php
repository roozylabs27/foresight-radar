<?php

namespace Tests\Feature\Security;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression guard for TENANT-001: the driving force PIC picker and pic_id
 * validation only ever see users of the caller's tenant.
 * See docs/audit/TENANT_INVITATION_READINESS_AUDIT.md.
 */
class DrivingForcePicTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private User $adminA;

    private User $adminB;

    private Dimension $dimension;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-driving-force', 'create-driving-force', 'edit-driving-force'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'driving-force', 'description' => $name]);
        }
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Admin']);
        $admin->givePermissionTo(['view-driving-force', 'create-driving-force', 'edit-driving-force']);

        $this->tenantA = Tenant::create(['name' => 'Acme Corporation', 'slug' => 'acme', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'Cyberdyne Systems', 'slug' => 'cyberdyne', 'status' => 'active']);

        $this->adminA = User::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Alice Admin A']);
        $this->adminA->assignRole('admin');
        $this->adminB = User::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Zed Secret Admin B']);
        $this->adminB->assignRole('admin');

        $env = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'External']);
        $this->dimension = Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Economy', 'environment_id' => $env->id]);
    }

    public function test_pic_options_list_only_the_callers_tenant_admins(): void
    {
        $this->actingAs($this->adminA)
            ->get('/driving-force')
            ->assertOk()
            ->assertInertia(function ($page) {
                $values = collect($page->toArray()['props']['users'])->pluck('value')->all();
                $this->assertContains($this->adminA->id, $values);
                $this->assertNotContains($this->adminB->id, $values);

                return $page;
            });
    }

    public function test_create_rejects_pic_from_another_tenant(): void
    {
        $this->actingAs($this->adminA)
            ->postJson('/driving-force', [
                'keyword' => 'Cross tenant pic',
                'description' => 'Assigning a user from another tenant',
                'dimension_id' => $this->dimension->id,
                'pic_id' => $this->adminB->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pic_id');

        $this->assertDatabaseMissing('driving_forces', ['keyword' => 'Cross tenant pic']);
    }

    public function test_create_accepts_pic_from_own_tenant(): void
    {
        $this->actingAs($this->adminA)
            ->postJson('/driving-force', [
                'keyword' => 'Own tenant pic',
                'description' => 'Assigning a user from the same tenant',
                'dimension_id' => $this->dimension->id,
                'pic_id' => $this->adminA->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('driving_forces', ['keyword' => 'Own tenant pic', 'pic' => $this->adminA->id, 'tenant_id' => $this->tenantA->id]);
    }

    public function test_update_rejects_pic_from_another_tenant(): void
    {
        $context = app(TenantContext::class);
        $context->setSuperuserBypass(true);
        $force = DrivingForce::create([
            'tenant_id' => $this->tenantA->id,
            'uuid' => (string) Uuid::uuid4(),
            'keyword' => 'Existing force',
            'description' => 'Owned by tenant A',
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->adminA->id,
            'pic' => $this->adminA->id,
            'status' => 'PENDING',
        ]);
        $context->reset();

        $this->actingAs($this->adminA)
            ->putJson("/driving-force/{$force->uuid}", [
                'keyword' => 'Existing force',
                'description' => 'Owned by tenant A',
                'dimension_id' => $this->dimension->id,
                'pic_id' => $this->adminB->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pic_id');

        $this->assertSame($this->adminA->id, (int) $force->fresh()->pic);
    }
}
