<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private function makePermission(string $name): Permission
    {
        return Permission::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            [
                'uuid' => (string) Uuid::uuid4(),
                'category' => 'general',
                'description' => "Permission for {$name}",
            ]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->makePermission('view-prioritizing');
        $this->makePermission('create-signal');

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['view-prioritizing', 'create-signal']);
    }

    public function test_visualization_routes_have_rate_limiting_headers(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/visualization/prioritizing/get-data');

        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit', 60);
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
    }

    public function test_signal_ingest_route_has_rate_limiting_headers(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/signals/ingest', []);

        // Even with validation failure (422), the throttle middleware executes and sets headers
        $this->assertTrue(in_array($response->status(), [200, 422]));
        $response->assertHeader('X-RateLimit-Limit', 15);
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
    }

    public function test_signal_ingest_route_enforces_rate_limit(): void
    {
        // Consume all 15 requests
        for ($i = 0; $i < 15; $i++) {
            $this->actingAs($this->user)->postJson('/signals/ingest', []);
        }

        // 16th request should hit rate limit (HTTP 429)
        $response = $this->actingAs($this->user)->postJson('/signals/ingest', []);
        $response->assertStatus(429);
    }
}
