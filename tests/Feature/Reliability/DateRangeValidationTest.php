<?php

namespace Tests\Feature\Reliability;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DateRangeValidationTest extends TestCase
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

    public function test_visualization_endpoints_handle_missing_or_malformed_date_parameters_gracefully(): void
    {
        $perm1 = $this->createPermission('view-prioritizing');
        $perm2 = $this->createPermission('view-registered-list');
        $perm3 = $this->createPermission('view-foresight-radar');

        $user = User::factory()->create();
        $user->givePermissionTo([$perm1, $perm2, $perm3]);

        $endpoints = [
            '/visualization/prioritizing/get-data',
            '/visualization/registered-list/get-data',
            '/visualization/foresight-radar/get-data',
        ];

        foreach ($endpoints as $url) {
            // Case 1: Missing date parameter entirely
            $res1 = $this->actingAs($user)->getJson($url);
            $res1->assertStatus(200);

            // Case 2: Malformed non-array date parameter
            $res2 = $this->actingAs($user)->getJson("{$url}?date=invalid-string");
            $res2->assertStatus(200);

            // Case 3: Single element date array
            $res3 = $this->actingAs($user)->getJson("{$url}?date[]=2026-01-01");
            $res3->assertStatus(200);
        }
    }
}
