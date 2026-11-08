<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\ForceAssessment;
use App\Models\ProjectDrivingForce;
use App\Models\ProjectParticipant;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use App\Support\DimensionTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roadmap R2.2, first slice: participants of an exercise with a role. The coordinator (project
 * policy) manages them. Once an exercise has participants, only contributing participants and the
 * coordinator give their own scores; everyone with view-scenario can still read.
 */
class ProjectParticipantTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $viewer;

    private User $expert;

    private ScenarioPlanningProject $project;

    private DrivingForce $force;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-scenario', 'manage-scenario'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'scenario', 'description' => $name]);
        }
        Role::firstOrCreate(['name' => 'bod', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'BOD'])->givePermissionTo('view-scenario');

        $this->manager = User::factory()->create(['name' => 'Coordinator']);
        $this->manager->givePermissionTo(['view-scenario', 'manage-scenario']);
        $this->viewer = User::factory()->create(['name' => 'Viewer']);
        $this->viewer->assignRole('bod');
        $this->expert = User::factory()->create(['name' => 'Expert']);
        $this->expert->assignRole('bod');

        DimensionTaxonomy::align();
        $this->force = DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(), 'dimension_id' => Dimension::where('code', 'LEGISLATIVE')->value('id'), 'created_by' => $this->manager->id,
            'keyword' => 'TKDN Regulation', 'description' => 'TKDN.', 'status' => 'APPROVED',
        ]);

        $this->actingAs($this->manager)->post(route('scenario-planning.store'), [
            'name' => 'Elektrifikasi 2030', 'focal_question' => 'How Indonesia vehicle electrification look like in 2030?',
            'purpose' => 'Strategic proposal toward 2030.', 'target_year' => now()->year + 4,
        ]);
        $this->project = ScenarioPlanningProject::latest('id')->firstOrFail();
        $this->actingAs($this->manager)->post(route('scenario-planning.confirm', $this->project));
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.store', $this->project), ['forces' => [$this->force->uuid]]);
        $this->actingAs($this->manager)->post(route('scenario-planning.forces.confirm', $this->project));
    }

    private function add(User $user, string $role, array $extra = [])
    {
        return $this->actingAs($this->manager)->post(route('scenario-planning.participants.store', $this->project), ['user' => $user->uuid, 'role' => $role] + $extra);
    }

    private function scoreMine(User $user)
    {
        $link = ProjectDrivingForce::where('project_id', $this->project->id)->sole();

        return $this->actingAs($user)->put(route('scenario-planning.assessment.mine', [$this->project, $link]), ['impact' => 7, 'uncertainty' => 6]);
    }

    public function test_the_coordinator_adds_participants_with_a_role_and_division(): void
    {
        $this->add($this->expert, 'expert', ['division' => 'R&D'])->assertSessionHasNoErrors();
        $this->add($this->viewer, 'viewer')->assertSessionHasNoErrors();

        $expert = ProjectParticipant::where('user_id', $this->expert->id)->sole();
        $this->assertSame(['expert', 'R&D', $this->project->id], [$expert->role, $expert->division, $expert->project_id]);

        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $this->project))
            ->assertInertia(fn ($page) => $page
                ->has('participants', 2)
                ->where('participants.0.user.name', 'Expert')
                ->where('participants.0.role', 'expert')
                ->where('participants.0.division', 'R&D')
                ->has('participantRoles', 4));
    }

    public function test_participants_are_validated_unique_and_editable(): void
    {
        $this->add($this->expert, 'guru')->assertSessionHasErrors('role');
        $this->actingAs($this->manager)->post(route('scenario-planning.participants.store', $this->project), ['user' => (string) Uuid::uuid4(), 'role' => 'expert'])->assertSessionHasErrors('user');
        $this->add($this->expert, 'expert')->assertSessionHasNoErrors();
        $this->add($this->expert, 'contributor')->assertSessionHasErrors('user');
        $this->assertSame(1, ProjectParticipant::count());

        $participant = ProjectParticipant::sole();
        $this->actingAs($this->manager)->put(route('scenario-planning.participants.update', [$this->project, $participant]), ['role' => 'contributor', 'division' => 'Sales'])->assertSessionHasNoErrors();
        $this->assertSame(['contributor', 'Sales'], [$participant->fresh()->role, $participant->fresh()->division]);

        $this->actingAs($this->manager)->delete(route('scenario-planning.participants.destroy', [$this->project, $participant]))->assertSessionHasNoErrors();
        $this->assertSame(0, ProjectParticipant::count());
    }

    public function test_only_the_coordinator_manages_participants(): void
    {
        $colleague = User::factory()->create();
        $colleague->givePermissionTo(['view-scenario', 'manage-scenario']);

        $this->actingAs($this->viewer)->post(route('scenario-planning.participants.store', $this->project), ['user' => $this->expert->uuid, 'role' => 'expert'])->assertForbidden();
        $this->actingAs($colleague)->post(route('scenario-planning.participants.store', $this->project), ['user' => $this->expert->uuid, 'role' => 'expert'])->assertForbidden();
        $this->assertSame(0, ProjectParticipant::count());

        $this->add($this->expert, 'expert');
        $other = $this->secondProject();
        $this->actingAs($this->manager)->delete(route('scenario-planning.participants.destroy', [$other, ProjectParticipant::sole()]))->assertNotFound();
    }

    public function test_once_listed_only_contributing_participants_and_the_coordinator_score(): void
    {
        $this->scoreMine($this->viewer)->assertSessionHasNoErrors();
        ForceAssessment::where('kind', 'individual')->delete();

        $this->add($this->expert, 'expert');
        $this->add($this->viewer, 'viewer');
        $outsider = User::factory()->create();
        $outsider->assignRole('bod');

        $this->scoreMine($this->expert)->assertSessionHasNoErrors();
        $this->scoreMine($this->viewer)->assertForbidden();
        $this->scoreMine($outsider)->assertForbidden();
        $this->scoreMine($this->manager)->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$this->expert->id, $this->manager->id], ForceAssessment::where('kind', 'individual')->pluck('assessor_id')->all());
        $this->actingAs($this->viewer)->get(route('scenario-planning.assessment.index', $this->project))->assertOk()->assertInertia(fn ($page) => $page->where('canScore', false));
        $this->actingAs($this->expert)->get(route('scenario-planning.assessment.index', $this->project))->assertInertia(fn ($page) => $page->where('canScore', true));
    }

    private function secondProject(): ScenarioPlanningProject
    {
        $this->actingAs($this->manager)->post(route('scenario-planning.store'), [
            'name' => 'Lain', 'focal_question' => 'What will retail look like in 2030 in Indonesia?',
            'purpose' => 'Other.', 'target_year' => now()->year + 4,
        ]);

        return ScenarioPlanningProject::latest('id')->firstOrFail();
    }
}
