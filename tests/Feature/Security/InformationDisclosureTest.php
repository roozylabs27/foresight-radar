<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InformationDisclosureTest extends TestCase
{
    use RefreshDatabase;

    private function createPermission(string $name): Permission
    {
        return Permission::create([
            'name' => $name,
            'uuid' => Uuid::uuid1(),
            'guard_name' => 'web',
            'category' => 'report',
            'description' => "Permission for {$name}",
        ]);
    }

    public function test_visualization_fetch_does_not_leak_raw_sql_exceptions_in_error_response(): void
    {
        $perm1 = $this->createPermission('view-prioritizing');
        $perm2 = $this->createPermission('view-registered-list');
        $perm3 = $this->createPermission('export-registered-list');
        $perm4 = $this->createPermission('view-foresight-radar');

        $user = User::factory()->create();
        $user->givePermissionTo([$perm1, $perm2, $perm3, $perm4]);

        $endpoints = [
            '/visualization/registered-list/get-data',
            '/visualization/registered-list/export-data',
            '/visualization/prioritizing/get-data',
            '/visualization/foresight-radar/get-data',
        ];

        foreach ($endpoints as $url) {
            $response = $this->actingAs($user)->getJson($url);

            if ($response->getStatusCode() === 500) {
                $content = method_exists($response, 'streamedContent') ? $response->streamedContent() : $response->getContent();
                $json = json_decode($content, true) ?? [];
                $this->assertArrayNotHasKey('errors', $json, "Raw exception string leaked under errors key at {$url}!");
                $this->assertEquals('An unexpected server error occurred while retrieving data.', $json['message'] ?? '');
            } else {
                $this->assertSame(200, $response->getStatusCode());
            }
        }
    }
}
