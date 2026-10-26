<?php

namespace Tests\Feature;

use App\Exceptions\TenantSecurityException;
use App\Models\Dimension;
use App\Models\Environment;
use App\Models\Signal;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private User $userB;

    private Dimension $dimension;

    private TimeHorizon $timeHorizon;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two distinct tenants
        $this->tenantA = Tenant::create([
            'id' => 10,
            'uuid' => (string) Uuid::uuid4(),
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'id' => 20,
            'uuid' => (string) Uuid::uuid4(),
            'name' => 'Cyberdyne Systems',
            'slug' => 'cyberdyne-sys',
            'status' => 'active',
        ]);

        // Create users strictly bound to each tenant
        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->userB = User::factory()->create(['tenant_id' => $this->tenantB->id]);

        // Base taxonomies
        $env = Environment::create(['name' => 'Tech', 'uuid' => (string) Uuid::uuid4()]);
        $this->dimension = Dimension::create([
            'environment_id' => $env->id,
            'name' => 'Technological',
            'code' => 'T',
            'uuid' => (string) Uuid::uuid4(),
        ]);
        $this->timeHorizon = TimeHorizon::create([
            'name' => 'Medium Term',
            'code' => 'M',
            'uuid' => (string) Uuid::uuid4(),
        ]);

        // Ensure permissions exist
        Permission::firstOrCreate(['name' => 'view-signal', 'guard_name' => 'web'], [
            'uuid' => (string) Uuid::uuid4(),
            'category' => 'signals',
            'description' => 'View signals',
        ]);
        Permission::firstOrCreate(['name' => 'create-signal', 'guard_name' => 'web'], [
            'uuid' => (string) Uuid::uuid4(),
            'category' => 'signals',
            'description' => 'Create signals',
        ]);
        $this->userA->givePermissionTo(['view-signal', 'create-signal']);
        $this->userB->givePermissionTo(['view-signal', 'create-signal']);
    }

    public function test_user_a_cannot_read_tenant_b_signals_via_eloquent(): void
    {
        // Act as User A (sets TenantContext to Tenant A)
        $this->actingAs($this->userA);
        $context = app(TenantContext::class);
        $context->setTenant($this->tenantA);

        // Directly create a signal for Tenant B bypassing scope
        $context->setSuperuserBypass(true);
        $sourceB = Source::create([
            'tenant_id' => $this->tenantB->id,
            'title' => 'Secret Source Cyberdyne',
            'url' => 'https://cyberdyne.example.com',
            'raw_content' => 'Confidential technical report content on robotics.',
            'content_hash' => hash('sha256', 'Confidential technical report content on robotics.'),
            'created_by' => $this->userB->id,
        ]);
        $signalB = Signal::create([
            'tenant_id' => $this->tenantB->id,
            'source_id' => $sourceB->id,
            'dimension_id' => $this->dimension->id,
            'title' => 'Secret AI Signal from Cyberdyne',
            'summary' => 'Confidential R&D analysis',
            'evidence_quote' => 'Verbatim evidence quote from Cyberdyne.',
            'significance' => 'High',
            'confidence_score' => 0.95,
        ]);
        $context->setSuperuserBypass(false);

        // Now query as User A
        $context->setTenant($this->tenantA);
        $visibleSignals = Signal::all();

        $this->assertCount(0, $visibleSignals);
        $this->assertNull(Signal::find($signalB->id));
        $this->assertNull(Signal::where('uuid', $signalB->uuid)->first());
    }

    public function test_user_a_receives_404_when_accessing_tenant_b_signal_by_uuid(): void
    {
        // Create Tenant B source & signal
        $context = app(TenantContext::class);
        $context->setSuperuserBypass(true);
        $sourceB = Source::create([
            'tenant_id' => $this->tenantB->id,
            'title' => 'Source B',
            'url' => 'https://cyberdyne.example.com/2',
            'raw_content' => 'Source B raw content text.',
            'content_hash' => hash('sha256', 'Source B raw content text.'),
            'created_by' => $this->userB->id,
        ]);
        $signalB = Signal::create([
            'tenant_id' => $this->tenantB->id,
            'source_id' => $sourceB->id,
            'dimension_id' => $this->dimension->id,
            'title' => 'Cyberdyne Confidential Signal',
            'summary' => 'Top secret summary',
            'evidence_quote' => 'Verbatim evidence quote from Cyberdyne.',
            'significance' => 'High',
            'confidence_score' => 0.90,
        ]);
        $context->setSuperuserBypass(false);

        // User A attempts to access Tenant B's signal directly via HTTP
        $response = $this->actingAs($this->userA)->get("/signals/{$signalB->uuid}");

        $response->assertStatus(404);
    }

    public function test_creating_signal_automatically_assigns_authenticated_tenant_id(): void
    {
        $this->actingAs($this->userA);
        app(TenantContext::class)->setTenant($this->tenantA);

        $sourceA = Source::create([
            'title' => 'Source Acme',
            'url' => 'https://acme.example.com',
            'raw_content' => 'Acme strategic research text.',
            'content_hash' => hash('sha256', 'Acme strategic research text.'),
            'created_by' => $this->userA->id,
        ]);

        $signalA = Signal::create([
            'source_id' => $sourceA->id,
            'dimension_id' => $this->dimension->id,
            'title' => 'Acme Public Signal',
            'summary' => 'Summary for Acme',
            'evidence_quote' => 'Verbatim evidence quote from Acme.',
            'significance' => 'Medium',
            'confidence_score' => 0.85,
        ]);

        $this->assertEquals($this->tenantA->id, $sourceA->tenant_id);
        $this->assertEquals($this->tenantA->id, $signalA->tenant_id);
    }

    public function test_tampered_tenant_id_during_creation_is_blocked_with_security_exception(): void
    {
        $this->actingAs($this->userA);
        app(TenantContext::class)->setTenant($this->tenantA);

        $this->expectException(TenantSecurityException::class);

        // User A attempts to create data stamped with Tenant B's ID
        Signal::create([
            'tenant_id' => $this->tenantB->id,
            'dimension_id' => $this->dimension->id,
            'title' => 'Tampered Injected Signal',
            'summary' => 'Injected summary',
            'significance' => 'Low',
        ]);
    }

    public function test_suspended_tenant_user_is_blocked_at_middleware(): void
    {
        $this->tenantA->update(['status' => 'suspended']);

        $response = $this->actingAs($this->userA)->get('/dashboard');

        $response->assertStatus(403);
    }

    public function test_platform_developer_can_access_without_tenant(): void
    {
        Permission::firstOrCreate(['name' => 'view-dashboard', 'guard_name' => 'web'], [
            'uuid' => (string) Uuid::uuid4(),
            'category' => 'dashboard',
            'description' => 'View dashboard',
        ]);

        $developerRole = Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'web'], [
            'uuid' => (string) Uuid::uuid4(),
            'display_name' => 'Developer',
        ]);
        $developerRole->givePermissionTo('view-dashboard');

        $developer = User::factory()->create([
            'tenant_id' => null,
        ]);
        $developer->assignRole($developerRole);

        // Middleware allows developer with tenant_id = null
        $response = $this->actingAs($developer)->get('/dashboard');

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
