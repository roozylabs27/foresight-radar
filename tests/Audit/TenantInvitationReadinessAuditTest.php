<?php

namespace Tests\Audit;

use App\Enums\LlmJobStatus;
use App\Jobs\ExtractSignals;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\Signal;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reproductions for docs/audit/TENANT_INVITATION_READINESS_AUDIT.md.
 *
 * Each test asserts the secure requirement, so a failing test is the proof
 * of the finding named in its docblock. This directory is outside the
 * default Unit/Feature suites; run it with `php artisan test tests/Audit`.
 * Move a test into tests/Feature once its finding is fixed.
 */
class TenantInvitationReadinessAuditTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $bodA;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Admin']);
        $bod = Role::firstOrCreate(['name' => 'bod', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Board of Directors']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Super Admin']);

        $viewDf = Permission::firstOrCreate(['name' => 'view-driving-force', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'df', 'description' => 'view']);
        $viewSignal = Permission::firstOrCreate(['name' => 'view-signal', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'signals', 'description' => 'view']);
        $manageMembers = Permission::firstOrCreate(['name' => 'manage-members', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'organization', 'description' => 'manage']);
        $admin->givePermissionTo([$viewDf, $viewSignal, $manageMembers]);
        $bod->givePermissionTo([$viewSignal]);

        $this->tenantA = Tenant::create(['name' => 'Acme Corporation', 'slug' => 'acme', 'status' => 'active', 'max_users' => 15]);
        $this->tenantB = Tenant::create(['name' => 'Cyberdyne Systems', 'slug' => 'cyberdyne', 'status' => 'active', 'max_users' => 15]);

        $this->adminA = User::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Alice Admin A', 'email' => 'alice@acme.test']);
        $this->adminA->assignRole('admin');
        $this->bodA = User::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Bob Board A', 'email' => 'bob@acme.test']);
        $this->bodA->assignRole('bod');
        $this->adminB = User::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Zed Secret Admin B', 'email' => 'zed@cyberdyne.test']);
        $this->adminB->assignRole('admin');
    }

    /** INVITE-006: the only admin can demote themselves, leaving the tenant without an admin. */
    public function test_last_admin_cannot_demote_self(): void
    {
        $this->actingAs($this->adminA)
            ->patch("/settings/members/{$this->adminA->uuid}/role", ['role' => 'bod']);

        $this->assertTrue($this->adminA->fresh()->hasRole('admin'));
    }

    /** Positive control: model restoration ignores the stale scope left by a previous job. */
    public function test_queue_worker_restores_job_for_a_different_tenant_after_previous_job(): void
    {
        Queue::fake();
        $context = app(TenantContext::class);
        $context->setSuperuserBypass(true);
        $sourceB = Source::create([
            'tenant_id' => $this->tenantB->id,
            'uuid' => (string) Uuid::uuid4(),
            'title' => 'B source',
            'raw_content' => 'text',
            'content_hash' => str_repeat('a', 64),
            'extraction_status' => LlmJobStatus::QUEUED,
        ]);
        $payload = serialize(new ExtractSignals($sourceB));
        $context->reset();

        // State left behind in the singleton by a previous job of tenant A.
        $context->setTenantId($this->tenantA->id);

        $restored = unserialize($payload);
        $this->assertSame($sourceB->id, $restored->source->id);
    }

    /** TENANT-005: signal review accepts another tenant's driving force id and writes the pivot. */
    public function test_signal_cannot_be_linked_to_other_tenants_driving_force(): void
    {
        $review = Permission::firstOrCreate(['name' => 'review-signal', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'signals', 'description' => 'review']);
        $this->adminA->givePermissionTo($review);

        $context = app(TenantContext::class);
        $context->setSuperuserBypass(true);
        $env = Environment::create(['name' => 'Tech', 'uuid' => (string) Uuid::uuid4()]);
        $dim = Dimension::create(['environment_id' => $env->id, 'name' => 'Technological', 'code' => 'T', 'uuid' => (string) Uuid::uuid4()]);
        $forceB = DrivingForce::create([
            'tenant_id' => $this->tenantB->id, 'uuid' => (string) Uuid::uuid4(), 'dimension_id' => $dim->id,
            'created_by' => $this->adminB->id, 'keyword' => 'B secret force', 'description' => 'B only', 'status' => 'APPROVED',
        ]);
        $sourceA = Source::create([
            'tenant_id' => $this->tenantA->id, 'uuid' => (string) Uuid::uuid4(), 'title' => 'A source',
            'raw_content' => 'Quote in the source.', 'content_hash' => str_repeat('c', 64),
        ]);
        $signalA = Signal::create([
            'tenant_id' => $this->tenantA->id, 'uuid' => (string) Uuid::uuid4(), 'source_id' => $sourceA->id,
            'title' => 'A signal', 'summary' => 'S', 'evidence_quote' => 'Quote in the source.', 'quote_verified' => true,
        ]);
        $context->reset();

        $this->actingAs($this->adminA)
            ->postJson("/signals/{$signalA->uuid}/review", ['action' => 'accept_link', 'driving_force_id' => $forceB->id]);

        $this->assertDatabaseMissing('signal_driving_force', ['signal_id' => $signalA->id, 'driving_force_id' => $forceB->id]);
    }

    /** Positive control: removing a member revokes access outside the testing fallback. */
    public function test_removed_member_is_denied_outside_testing_environment(): void
    {
        $this->actingAs($this->adminA)->delete("/settings/members/{$this->bodA->uuid}");
        $this->assertNull($this->bodA->fresh()->tenant_id);

        app()['env'] = 'production';
        app(TenantContext::class)->reset();

        $this->actingAs($this->bodA->fresh())->get('/signals')->assertForbidden();
    }

    /** Positive control: cross-tenant member mutation is rejected. */
    public function test_admin_cannot_change_role_of_other_tenant_member(): void
    {
        $this->actingAs($this->adminA)
            ->patch("/settings/members/{$this->adminB->uuid}/role", ['role' => 'bod'])
            ->assertForbidden();

        $this->assertTrue($this->adminB->fresh()->hasRole('admin'));
    }

    /** Positive control: another tenant's invitation is invisible to route binding. */
    public function test_admin_cannot_cancel_other_tenant_invitation(): void
    {
        app(TenantContext::class)->setSuperuserBypass(true);
        $invB = TenantInvitation::create(['tenant_id' => $this->tenantB->id, 'email' => 'x@b.test', 'role' => 'bod', 'token' => str_repeat('t', 40), 'expires_at' => now()->addDay()]);
        app(TenantContext::class)->reset();

        $this->actingAs($this->adminA)
            ->delete("/settings/members/invitations/{$invB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('tenant_invitations', ['id' => $invB->id]);
    }
}
