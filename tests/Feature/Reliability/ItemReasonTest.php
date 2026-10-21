<?php

namespace Tests\Feature\Reliability;

use App\Models\ActionReason;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Environment;
use App\Models\Priority;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ItemReasonTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Dimension $dimension;
    private StatusAction $statusAction;
    private TimeHorizon $timeHorizon;
    private Priority $priority;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-approval-items', 'view-closed-items'] as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'uuid' => Uuid::uuid1(),
                'category' => 'general',
                'description' => "Permission for {$name}",
            ]);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['view-approval-items', 'view-closed-items']);

        $env = Environment::create(['name' => 'General', 'uuid' => Uuid::uuid1()]);
        $this->dimension = Dimension::create(['name' => 'Technology', 'symbol' => 'T', 'environment_id' => $env->id, 'uuid' => Uuid::uuid1()]);
        $this->statusAction = StatusAction::create(['name' => 'Act', 'symbol' => 'A', 'uuid' => Uuid::uuid1()]);
        $this->timeHorizon = TimeHorizon::create(['name' => 'Mid Term', 'code' => 'MT', 'uuid' => Uuid::uuid1()]);
        $this->priority = Priority::create(['uuid' => Uuid::uuid1(), 'name' => 'High', 'color' => '#ff4d4f']);
    }

    private function createRatedDrivingForce(string $keyword, string $status): DrivingForceRating
    {
        $df = DrivingForce::create([
            'uuid' => Uuid::uuid1(),
            'dimension_id' => $this->dimension->id,
            'created_by' => $this->user->id,
            'pic' => $this->user->id,
            'keyword' => $keyword,
            'description' => "{$keyword} description",
            'status' => $status,
            'closed_at' => $status === 'CLOSED' ? now() : null,
        ]);

        return DrivingForceRating::create([
            'driving_force_id' => $df->id,
            'time_horizon_id' => $this->timeHorizon->id,
            'status_action_id' => $this->statusAction->id,
            'impact_analysis' => 8,
            'uncertainty_analysis' => 7,
            'priority_id' => $this->priority->id,
        ]);
    }

    private function addReason(DrivingForceRating $rating, string $reason, $date, ?int $id = null): void
    {
        $actionReason = new ActionReason();
        if ($id !== null) {
            $actionReason->id = $id;
        }
        $actionReason->uuid = Uuid::uuid1();
        $actionReason->driving_force_rating_id = $rating->id;
        $actionReason->status_action_id = $this->statusAction->id;
        $actionReason->reason = $reason;
        $actionReason->date = $date;
        $actionReason->save();
    }

    /**
     * Seeds a target item whose own reasons are inserted newest-first, plus an
     * unrelated item owning the action_reasons row whose id equals the target
     * rating id (the row the old lookup by primary key used to return).
     */
    private function seedReasons(string $status): void
    {
        $target = $this->createRatedDrivingForce('Target Signal', $status);
        $other = $this->createRatedDrivingForce('Other Signal', $status);

        $this->addReason($other, 'Reason of another driving force', now(), $target->id);
        $this->addReason($target, 'Latest own reason', now()->addMinute());
        $this->addReason($target, 'Older own reason', now()->subDay());
    }

    private function reasonsByKeyword(string $uri): array
    {
        $response = $this->actingAs($this->user)->getJson($uri);
        $response->assertOk();

        return collect($response->json('data'))->pluck('reason', 'keyword')->all();
    }

    public function test_approval_items_return_latest_reason_of_own_rating(): void
    {
        $this->seedReasons('PENDING');

        $reasons = $this->reasonsByKeyword('/approval-items/fetch-data');

        $this->assertSame('Latest own reason', $reasons['Target Signal']);
        $this->assertSame('Reason of another driving force', $reasons['Other Signal']);
    }

    public function test_closed_items_return_latest_reason_of_own_rating(): void
    {
        $this->seedReasons('CLOSED');

        $reasons = $this->reasonsByKeyword('/closed-items/fetch-data');

        $this->assertSame('Latest own reason', $reasons['Target Signal']);
        $this->assertSame('Reason of another driving force', $reasons['Other Signal']);
    }

    public function test_item_without_reasons_returns_null_reason(): void
    {
        $this->createRatedDrivingForce('Unreasoned Signal', 'PENDING');

        $reasons = $this->reasonsByKeyword('/approval-items/fetch-data');

        $this->assertArrayHasKey('Unreasoned Signal', $reasons);
        $this->assertNull($reasons['Unreasoned Signal']);
    }
}
