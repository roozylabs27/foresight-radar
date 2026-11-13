<?php

namespace Tests\Feature\Security;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\Signal;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression guard for TENANT-005: an id or uuid from another tenant is
 * treated exactly like one that does not exist, so it can neither be written
 * nor be used to probe what another tenant holds.
 * See docs/audit/TENANT_INVITATION_READINESS_AUDIT.md.
 */
class TenantAwareExistsRulesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private User $adminA;

    private DrivingForce $forceA;

    private DrivingForce $otherForceA;

    private DrivingForce $forceB;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = ['review-signal', 'manage-cross-impact', 'manage-scenario', 'view-dashboard'];
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'general', 'description' => $name]);
        }
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Admin'])
            ->givePermissionTo($permissions);

        $this->tenantA = Tenant::create(['name' => 'Acme Corporation', 'slug' => 'acme', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'Cyberdyne Systems', 'slug' => 'cyberdyne', 'status' => 'active']);
        $this->adminA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->adminA->assignRole('admin');
        $adminB = User::factory()->create(['tenant_id' => $tenantB->id]);

        $env = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'External']);
        $dimension = Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Economy', 'environment_id' => $env->id]);
        $this->forceA = $this->force($this->tenantA, $dimension, $this->adminA, 'Force A');
        $this->otherForceA = $this->force($this->tenantA, $dimension, $this->adminA, 'Force A2');
        $this->forceB = $this->force($tenantB, $dimension, $adminB, 'Secret force B');
    }

    public function test_signal_review_cannot_link_another_tenants_driving_force(): void
    {
        $signal = $this->signalA();

        $this->actingAs($this->adminA)
            ->postJson("/signals/{$signal->uuid}/review", ['action' => 'accept_link', 'driving_force_id' => $this->forceB->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('driving_force_id');

        $this->assertDatabaseMissing('signal_driving_force', ['signal_id' => $signal->id, 'driving_force_id' => $this->forceB->id]);
        $this->assertSame('PENDING', $signal->fresh()->review_status);
    }

    public function test_signal_review_still_links_own_tenants_driving_force(): void
    {
        $signal = $this->signalA();

        $this->actingAs($this->adminA)
            ->postJson("/signals/{$signal->uuid}/review", ['action' => 'accept_link', 'driving_force_id' => $this->forceA->id])
            ->assertOk();

        $this->assertDatabaseHas('signal_driving_force', ['signal_id' => $signal->id, 'driving_force_id' => $this->forceA->id]);
    }

    public function test_cross_impact_treats_foreign_force_as_unknown(): void
    {
        $payload = fn (string $uuid) => ['source' => $this->forceA->uuid, 'target' => $uuid, 'score' => 2];

        $this->assertSameAsUnknown(
            fn () => $this->actingAs($this->adminA)->postJson('/visualization/cross-impact', $payload($this->forceB->uuid)),
            fn () => $this->actingAs($this->adminA)->postJson('/visualization/cross-impact', $payload((string) Uuid::uuid4())),
            'target',
        );
        $this->assertDatabaseMissing('cross_impacts', ['target_driving_force_id' => $this->forceB->id]);
    }

    public function test_simulation_treats_foreign_force_as_unknown(): void
    {
        $this->assertSameAsUnknown(
            fn () => $this->actingAs($this->adminA)->postJson('/dashboard/simulate', ['overrides' => [['force' => $this->forceB->uuid, 'impact' => 9]]]),
            fn () => $this->actingAs($this->adminA)->postJson('/dashboard/simulate', ['overrides' => [['force' => (string) Uuid::uuid4(), 'impact' => 9]]]),
            'overrides.0.force',
        );
    }

    /**
     * @param  callable(): TestResponse  $foreign
     * @param  callable(): TestResponse  $unknown
     */
    private function assertSameAsUnknown(callable $foreign, callable $unknown, string $field): void
    {
        $foreignResponse = $foreign();
        app(TenantContext::class)->reset();
        $unknownResponse = $unknown();

        $unknownResponse->assertStatus(422)->assertJsonValidationErrors($field);
        $this->assertSame(422, $foreignResponse->getStatusCode(), 'a foreign id must be rejected by validation, like an unknown one');
        $this->assertSame($unknownResponse->json('errors'), $foreignResponse->json('errors'));
    }

    private function force(Tenant $tenant, Dimension $dimension, User $owner, string $keyword): DrivingForce
    {
        return $this->asTenant($tenant, fn () => DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(), 'dimension_id' => $dimension->id, 'created_by' => $owner->id,
            'keyword' => $keyword, 'description' => $keyword, 'status' => 'APPROVED',
        ]));
    }

    private function signalA(): Signal
    {
        return $this->asTenant($this->tenantA, function () {
            $source = Source::create([
                'uuid' => (string) Uuid::uuid4(), 'title' => 'A source',
                'raw_content' => 'Quote in the source.', 'content_hash' => hash('sha256', 'a'),
            ]);

            return Signal::create([
                'uuid' => (string) Uuid::uuid4(), 'source_id' => $source->id, 'title' => 'A signal', 'summary' => 'S',
                'evidence_quote' => 'Quote in the source.', 'quote_verified' => true,
            ]);
        });
    }

    private function asTenant(Tenant $tenant, callable $create): mixed
    {
        $context = app(TenantContext::class);
        $context->setTenant($tenant);
        try {
            return $create();
        } finally {
            $context->reset();
        }
    }
}
