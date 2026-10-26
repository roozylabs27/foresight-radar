<?php

namespace Tests\Feature\Security;

use App\Models\Dimension;
use App\Models\Environment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression guard for TENANT-003: a suspended or deleted tenant is denied on
 * every request type, not only on page visits.
 * See docs/audit/TENANT_INVITATION_READINESS_AUDIT.md.
 */
class TenantStatusEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-signal', 'create-driving-force'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'general', 'description' => $name]);
        }
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Admin']);
        $role->givePermissionTo(['view-signal', 'create-driving-force']);

        $this->tenant = Tenant::create(['name' => 'Acme Corporation', 'slug' => 'acme', 'status' => 'active']);
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');
    }

    public function test_active_tenant_can_use_json_endpoints(): void
    {
        $this->actingAs($this->admin)->getJson('/signals/fetch')->assertOk();
    }

    public function test_suspended_tenant_is_denied_on_json_reads(): void
    {
        $this->tenant->update(['status' => 'suspended']);

        $this->actingAs($this->admin)->getJson('/signals/fetch')->assertForbidden();
    }

    public function test_suspended_tenant_is_denied_on_json_writes(): void
    {
        $env = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'External']);
        $dimension = Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Economy', 'environment_id' => $env->id]);
        $this->tenant->update(['status' => 'suspended']);

        $this->actingAs($this->admin)
            ->postJson('/driving-force', [
                'keyword' => 'Written while suspended',
                'description' => 'Should never be stored',
                'dimension_id' => $dimension->id,
                'pic_id' => $this->admin->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('driving_forces', ['keyword' => 'Written while suspended']);
    }

    public function test_soft_deleted_tenant_is_denied_on_json_reads(): void
    {
        $this->tenant->delete();

        $this->actingAs($this->admin)->getJson('/signals/fetch')->assertForbidden();
    }
}
