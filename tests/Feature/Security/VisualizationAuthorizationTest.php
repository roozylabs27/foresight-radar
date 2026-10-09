<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VisualizationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makePermission(string $name): Permission
    {
        return Permission::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'description' => $name,
            'category' => 'visualization',
            'guard_name' => 'web',
        ]);
    }

    public function test_unprivileged_authenticated_user_cannot_access_visualization_endpoints(): void
    {
        $user = User::factory()->create(); // Has no permissions assigned

        $endpoints = [
            '/visualization/prioritizing',
            '/visualization/prioritizing/get-data',
            '/visualization/registered-list',
            '/visualization/registered-list/get-data',
            '/visualization/registered-list/export-data',
            '/visualization/foresight-radar',
            '/visualization/foresight-radar/get-data',
        ];

        foreach ($endpoints as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertStatus(403, "Endpoint {$url} should return 403 Forbidden for unprivileged users!");
        }
    }

    public function test_authorized_user_can_access_visualization_endpoints(): void
    {
        $this->makePermission('view-prioritizing');
        $this->makePermission('view-registered-list');
        $this->makePermission('export-registered-list');
        $this->makePermission('view-foresight-radar');

        $user = User::factory()->create();
        $user->givePermissionTo([
            'view-prioritizing',
            'view-registered-list',
            'export-registered-list',
            'view-foresight-radar',
        ]);

        $response = $this->actingAs($user)->get('/visualization/prioritizing');
        $response->assertOk();

        $response = $this->actingAs($user)->get('/visualization/registered-list');
        $response->assertOk();

        $response = $this->actingAs($user)->get('/visualization/registered-list/export-data?date[]=2026-01-01&date[]=2026-12-31');
        $response->assertOk();

        $response = $this->actingAs($user)->get('/visualization/foresight-radar');
        $response->assertOk();
    }
}
