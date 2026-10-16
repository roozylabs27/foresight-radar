<?php

namespace Tests\Feature\Reasoning;

use App\Models\CrossImpact;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CrossImpactTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $viewer;
    private Dimension $dimension;

    protected function setUp(): void
    {
        parent::setUp();

        // Both permissions are created by the cross_impacts migration.
        foreach (['view-cross-impact', 'manage-cross-impact'] as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Uuid::uuid4(), 'category' => 'cross-impact', 'description' => $name]
            );
        }

        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo(['view-cross-impact', 'manage-cross-impact']);
        $this->viewer = User::factory()->create();
        $this->viewer->givePermissionTo('view-cross-impact');

        $environment = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Macro']);
        $this->dimension = Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Technology', 'environment_id' => $environment->id]);
    }

    private function force(string $keyword, string $status = 'APPROVED'): DrivingForce
    {
        return DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(),
            'keyword' => $keyword,
            'description' => 'Driving force used in the cross-impact matrix.',
            'status' => $status,
            'dimension_id' => $this->dimension->id,
            'pic' => $this->manager->id,
            'created_by' => $this->manager->id,
        ]);
    }

    private function score(DrivingForce $source, DrivingForce $target, int $score, ?string $note = null)
    {
        return $this->actingAs($this->manager)->postJson('/visualization/cross-impact', [
            'source' => $source->uuid,
            'target' => $target->uuid,
            'score' => $score,
            'note' => $note,
        ]);
    }

    public function test_viewer_can_read_but_not_score_and_others_are_forbidden(): void
    {
        $a = $this->force('Alpha');
        $b = $this->force('Bravo');

        $this->actingAs($this->viewer)->getJson('/visualization/cross-impact/get-data')->assertStatus(200);
        $this->actingAs($this->viewer)->get('/visualization/cross-impact')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Report/CrossImpact')->where('canManage', false));
        $this->actingAs($this->viewer)->postJson('/visualization/cross-impact', [
            'source' => $a->uuid, 'target' => $b->uuid, 'score' => 2,
        ])->assertStatus(403);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson('/visualization/cross-impact/get-data')->assertStatus(403);
    }

    public function test_matrix_scores_and_vester_roles(): void
    {
        $a = $this->force('Alpha');
        $b = $this->force('Bravo');
        $c = $this->force('Charlie');
        $e = $this->force('Echo');
        $f = $this->force('Foxtrot');
        $this->force('Pending Delta', 'PENDING');

        $this->score($a, $b, 3)->assertStatus(200);
        $this->score($a, $c, -2, 'Higher rates slow adoption')->assertStatus(200);
        $this->score($b, $c, 2);
        $this->score($c, $b, 1);
        $this->score($b, $a, 1);
        $this->score($e, $f, 1);

        $matrix = $this->actingAs($this->viewer)->getJson('/visualization/cross-impact/get-data')->json();

        // Only approved forces are in the matrix: 5 × 4 ordered pairs.
        $this->assertCount(5, $matrix['forces']);
        $this->assertSame(6, $matrix['scored_pairs']);
        $this->assertSame(20, $matrix['possible_pairs']);
        $this->assertContains(
            ['source' => $a->uuid, 'target' => $c->uuid, 'score' => -2, 'note' => 'Higher rates slow adoption'],
            $matrix['cells']
        );

        // Active sums 5, 3, 1, 1, 0 and passive sums 1, 4, 4, 0, 1 → both averages are 2.
        $this->assertEquals(['active' => 2, 'passive' => 2], $matrix['thresholds']);
        $summary = collect($matrix['summary'])->keyBy('keyword');
        $this->assertSame(['active' => 5, 'passive' => 1, 'net' => 1, 'role' => 'driver'], collect($summary['Alpha'])->only(['active', 'passive', 'net', 'role'])->all());
        $this->assertSame(['active' => 3, 'passive' => 4, 'role' => 'critical'], collect($summary['Bravo'])->only(['active', 'passive', 'role'])->all());
        $this->assertSame('indicator', $summary['Charlie']['role']);
        $this->assertSame('buffer', $summary['Echo']['role']);
        $this->assertSame('buffer', $summary['Foxtrot']['role']);
        $this->assertSame('Kritis', $summary['Bravo']['role_label']);
        $this->assertSame('Bravo', $matrix['summary'][0]['keyword']);

        // Loops: Alpha⇄Bravo (3, 1), Bravo⇄Charlie (2, 1), Alpha→Charlie→Bravo→Alpha (−2, 1, 1).
        $loops = $matrix['loops'];
        $this->assertSame(3, $loops['count']);
        $this->assertFalse($loops['truncated']);
        $this->assertSame([$a->uuid, $b->uuid], $loops['loops'][0]['nodes']);
        $this->assertSame('R', $loops['loops'][0]['polarity']);
        $this->assertSame(0.577, $loops['loops'][0]['strength']);
        $this->assertSame([$b->uuid, $c->uuid], $loops['loops'][1]['nodes']);
        $this->assertSame([$a->uuid, $c->uuid, $b->uuid], $loops['loops'][2]['nodes']);
        $this->assertSame('B', $loops['loops'][2]['polarity']);
        $this->assertSame(0.42, $loops['loops'][2]['strength']);
    }

    public function test_scoring_returns_the_refreshed_loops(): void
    {
        $a = $this->force('Alpha');
        $b = $this->force('Bravo');

        $this->score($a, $b, 2)->assertJsonPath('matrix.loops.count', 0);
        $this->score($b, $a, -2)
            ->assertJsonPath('matrix.loops.count', 1)
            ->assertJsonPath('matrix.loops.loops.0.polarity', 'B');
    }

    public function test_rescoring_updates_the_pair_and_zero_without_note_removes_it(): void
    {
        $a = $this->force('Alpha');
        $b = $this->force('Bravo');

        $this->score($a, $b, 2);
        $this->score($a, $b, -1, 'Reversed after review')->assertJsonPath('message', 'Cross-impact disimpan.');
        $this->assertDatabaseCount('cross_impacts', 1);
        $this->assertSame(-1, CrossImpact::first()->score);
        $this->assertSame($this->manager->id, CrossImpact::first()->updated_by);

        $this->score($a, $b, 0, 'Checked: no effect');
        $this->assertSame(0, CrossImpact::first()->score);

        $this->score($a, $b, 0)->assertJsonPath('message', 'Cross-impact dihapus.');
        $this->assertDatabaseCount('cross_impacts', 0);
    }

    public function test_rejects_self_links_unapproved_forces_and_out_of_range_scores(): void
    {
        $a = $this->force('Alpha');
        $b = $this->force('Bravo');
        $pending = $this->force('Pending Delta', 'PENDING');

        $this->score($a, $a, 2)->assertStatus(422)->assertJsonValidationErrors('target');
        $this->score($a, $pending, 2)->assertStatus(422)->assertJsonValidationErrors('target');
        $this->score($a, $b, 4)->assertStatus(422)->assertJsonValidationErrors('score');
        $this->assertDatabaseCount('cross_impacts', 0);
    }
}
