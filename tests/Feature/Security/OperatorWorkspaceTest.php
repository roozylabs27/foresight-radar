<?php

namespace Tests\Feature\Security;

use App\Exceptions\TenantSecurityException;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression guard for TENANT-007: a platform operator (no tenant of their own)
 * works inside one workspace they pick. Without one they see no tenant data and
 * cannot create any; nothing silently lands in tenant 1.
 * See docs/audit/TENANT_INVITATION_READINESS_AUDIT.md.
 */
class OperatorWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $operator;

    private User $memberA;

    private Dimension $dimension;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = ['view-driving-force', 'create-driving-force'];
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'driving-force', 'description' => $name]);
        }
        Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Developer'])
            ->givePermissionTo($permissions);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Admin'])
            ->givePermissionTo($permissions);

        $this->tenantA = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active']);
        $this->tenantB = Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active']);

        $this->operator = User::factory()->create(['tenant_id' => null]);
        $this->operator->assignRole('developer');
        $this->memberA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->memberA->assignRole('admin');
        $memberB = User::factory()->create(['tenant_id' => $this->tenantB->id]);

        // Each organisation owns its dimensions (plan part B1); tenant A got the default taxonomy when it was created.
        $this->dimension = Dimension::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->where('code', 'ECONOMIC')->firstOrFail();

        $this->force($this->tenantA, $this->memberA, 'Acme Force');
        $this->force($this->tenantB, $memberB, 'Cyberdyne Force');
    }

    public function test_operator_without_workspace_sees_no_tenant_data(): void
    {
        $this->actingAs($this->operator)
            ->getJson('/driving-force/fetch-data')
            ->assertOk()
            ->assertDontSee('Acme Force')
            ->assertDontSee('Cyberdyne Force');
    }

    public function test_operator_without_workspace_cannot_create_tenant_data(): void
    {
        $this->actingAs($this->operator)
            ->postJson('/driving-force', $this->forcePayload('Orphan Force'))
            ->assertForbidden()
            ->assertJsonPath('message', TenantSecurityException::WORKSPACE_REQUIRED);

        $this->assertDatabaseMissing('driving_forces', ['keyword' => 'Orphan Force']);
    }

    public function test_operator_sees_and_writes_only_the_picked_workspace(): void
    {
        $this->actingAs($this->operator)
            ->post(route('operator.workspace'), ['tenant' => $this->tenantA->uuid])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->operator)
            ->getJson('/driving-force/fetch-data')
            ->assertOk()
            ->assertSee('Acme Force')
            ->assertDontSee('Cyberdyne Force');

        $this->actingAs($this->operator)
            ->postJson('/driving-force', $this->forcePayload('Operator Force'))
            ->assertSuccessful();

        $this->assertDatabaseHas('driving_forces', ['keyword' => 'Operator Force', 'tenant_id' => $this->tenantA->id]);
    }

    public function test_leaving_the_workspace_hides_its_data_again(): void
    {
        $this->actingAs($this->operator)->post(route('operator.workspace'), ['tenant' => $this->tenantA->uuid]);
        $this->actingAs($this->operator)->post(route('operator.workspace'), ['tenant' => null])->assertSessionHasNoErrors();

        $this->actingAs($this->operator)
            ->getJson('/driving-force/fetch-data')
            ->assertDontSee('Acme Force');
    }

    public function test_deleted_tenant_cannot_be_picked(): void
    {
        $this->tenantB->delete();

        $this->actingAs($this->operator)
            ->post(route('operator.workspace'), ['tenant' => $this->tenantB->uuid])
            ->assertSessionHasErrors('tenant');
    }

    public function test_tenant_members_cannot_switch_workspace(): void
    {
        $this->actingAs($this->memberA)
            ->post(route('operator.workspace'), ['tenant' => $this->tenantB->uuid])
            ->assertForbidden();
    }

    public function test_only_operators_receive_the_workspace_list(): void
    {
        $this->actingAs($this->operator)->get('/profile')
            ->assertInertia(fn ($page) => $page->has('auth.operator.workspaces', Tenant::count())->where('auth.operator.current', null));

        $this->actingAs($this->memberA)->get('/profile')
            ->assertInertia(fn ($page) => $page->where('auth.operator', null));
    }

    private function forcePayload(string $keyword): array
    {
        return [
            'keyword' => $keyword,
            'description' => 'A short description.',
            'dimension_id' => $this->dimension->id,
            'pic_id' => $this->memberA->id,
        ];
    }

    private function force(Tenant $tenant, User $owner, string $keyword): DrivingForce
    {
        $context = app(TenantContext::class);
        $context->setTenant($tenant);
        try {
            return DrivingForce::create([
                'uuid' => (string) Uuid::uuid4(), 'dimension_id' => $this->dimension->id, 'created_by' => $owner->id,
                'keyword' => $keyword, 'description' => $keyword, 'status' => 'APPROVED',
            ]);
        } finally {
            $context->reset();
        }
    }
}
