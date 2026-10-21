<?php

namespace Tests\Feature\Reliability;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\TimeHorizon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TimeHorizonUuidTest extends TestCase
{
    use RefreshDatabase;

    private function createPermission(string $name): Permission
    {
        return Permission::create([
            'name' => $name,
            'uuid' => Uuid::uuid1(),
            'guard_name' => 'web',
            'category' => 'time-horizon',
            'description' => "Permission for {$name}",
        ]);
    }

    public function test_updating_time_horizon_preserves_original_rating_uuid(): void
    {
        $perm1 = $this->createPermission('view-time-horizon');
        $perm2 = $this->createPermission('create-time-horizon');

        $user = User::factory()->create();
        $user->givePermissionTo([$perm1, $perm2]);

        $env = Environment::create(['name' => 'General', 'uuid' => Uuid::uuid1()]);
        $dim = Dimension::create(['name' => 'Tech', 'uuid' => Uuid::uuid1(), 'environment_id' => $env->id]);
        $df = DrivingForce::create([
            'dimension_id' => $dim->id,
            'created_by' => $user->id,
            'keyword' => 'AI',
            'description' => 'Artificial intelligence',
            'status' => 'PENDING',
            'uuid' => Uuid::uuid1(),
        ]);

        $th1 = TimeHorizon::create(['name' => 'Short', 'code' => 'ST', 'uuid' => Uuid::uuid1()]);
        $th2 = TimeHorizon::create(['name' => 'Medium', 'code' => 'MT', 'uuid' => Uuid::uuid1()]);

        // First creation
        $res1 = $this->actingAs($user)->postJson("/time-horizon/{$df->id}", [
            'time_horizon_id' => $th1->id,
        ]);
        $res1->assertStatus(200);

        $rating = DrivingForceRating::where('driving_force_id', $df->id)->firstOrFail();
        $originalUuid = $rating->uuid;
        $this->assertNotEmpty($originalUuid);

        // Second update with different time horizon
        $res2 = $this->actingAs($user)->postJson("/time-horizon/{$df->id}", [
            'time_horizon_id' => $th2->id,
        ]);
        $res2->assertStatus(200);

        $rating->refresh();
        $this->assertEquals($originalUuid, $rating->uuid, 'Rating UUID changed on update!');
        $this->assertEquals($th2->id, $rating->time_horizon_id);
    }
}
