<?php

namespace Tests\Feature\ScenarioPlanning;

use App\Models\ScenarioPlanningProject;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roadmap R0.1: a scenario planning project is the container for the nine
 * steps, starting with its key focal issue (question, purpose, target year).
 * See docs/product/SCENARIO_PLANNING_FEATURE_SPECIFICATION.md, step 1.
 */
class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $viewer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view-scenario', 'manage-scenario'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'scenario', 'description' => $name]);
        }
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'Admin'])
            ->givePermissionTo(['view-scenario', 'manage-scenario']);
        Role::firstOrCreate(['name' => 'bod', 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'display_name' => 'BOD'])
            ->givePermissionTo(['view-scenario']);

        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo(['view-scenario', 'manage-scenario']);
        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('bod');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Elektrifikasi 2030',
            'focal_question' => 'How Indonesia vehicle (four wheeler) electrification look like in 2030?',
            'purpose' => 'CP function develops strategic proposal toward ID electrification 2030 scenario.',
            'target_year' => now()->year + 4,
        ];
    }

    private function project(User $owner, array $overrides = []): ScenarioPlanningProject
    {
        $this->actingAs($owner)->post(route('scenario-planning.store'), $this->payload($overrides))->assertSessionHasNoErrors();

        return ScenarioPlanningProject::latest('id')->firstOrFail();
    }

    public function test_manager_creates_a_project_with_its_focal_issue(): void
    {
        $response = $this->actingAs($this->manager)->post(route('scenario-planning.store'), $this->payload([
            'geography' => 'Indonesia', 'industry' => 'Otomotif roda empat',
        ]));

        $project = ScenarioPlanningProject::sole();
        $response->assertRedirect(route('scenario-planning.show', $project));
        $this->assertSame($this->manager->tenant_id, $project->tenant_id);
        $this->assertSame($this->manager->id, $project->created_by);
        $this->assertSame('draft', $project->status);
        $this->assertSame(now()->year + 4, $project->target_year);
        $this->assertSame('Indonesia', $project->geography);
        $this->assertNull($project->confirmed_at);
    }

    public function test_focal_question_purpose_and_target_year_are_required(): void
    {
        $this->actingAs($this->manager)
            ->post(route('scenario-planning.store'), ['name' => 'Tanpa isi'])
            ->assertSessionHasErrors(['focal_question', 'purpose', 'target_year']);

        $this->actingAs($this->manager)
            ->post(route('scenario-planning.store'), $this->payload(['target_year' => now()->year]))
            ->assertSessionHasErrors('target_year');

        $this->assertSame(0, ScenarioPlanningProject::count());
    }

    public function test_viewer_can_read_but_not_create(): void
    {
        $project = $this->project($this->manager);

        $this->actingAs($this->viewer)->get(route('scenario-planning.index'))->assertOk();
        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $project))->assertOk();
        $this->actingAs($this->viewer)->post(route('scenario-planning.store'), $this->payload())->assertForbidden();
    }

    public function test_projects_are_isolated_per_tenant(): void
    {
        $project = $this->project($this->manager);
        $other = Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active']);
        $outsider = User::factory()->create(['tenant_id' => $other->id]);
        $outsider->givePermissionTo(['view-scenario', 'manage-scenario']);
        app(TenantContext::class)->reset();

        $this->actingAs($outsider)->get(route('scenario-planning.show', $project))->assertNotFound();
        $this->actingAs($outsider)->get(route('scenario-planning.index'))
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Index')->has('projects', 0));
    }

    public function test_owner_must_belong_to_the_same_tenant(): void
    {
        $other = Tenant::create(['name' => 'Cyberdyne', 'slug' => 'cyberdyne', 'status' => 'active']);
        $foreign = User::factory()->create(['tenant_id' => $other->id]);

        $this->actingAs($this->manager)
            ->post(route('scenario-planning.store'), $this->payload(['owner' => $foreign->uuid]))
            ->assertSessionHasErrors('owner');

        $this->actingAs($this->manager)
            ->post(route('scenario-planning.store'), $this->payload(['owner' => $this->manager->uuid]))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->manager->id, ScenarioPlanningProject::sole()->owner_user_id);
    }

    public function test_confirming_the_focal_issue_records_who_and_when_and_opens_the_next_steps(): void
    {
        $project = $this->project($this->manager);

        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $project))
            ->assertInertia(fn ($page) => $page->component('ScenarioPlanning/Show')
                ->where('steps.0.status', 'in_progress')
                ->where('steps.1.status', 'locked')
                ->has('steps', 9));

        $this->actingAs($this->manager)->post(route('scenario-planning.confirm', $project))->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame($this->manager->id, $project->confirmed_by);
        $this->assertNotNull($project->confirmed_at);
        $this->assertSame('active', $project->status);
        $this->actingAs($this->viewer)->get(route('scenario-planning.show', $project))
            ->assertInertia(fn ($page) => $page->where('steps.0.status', 'done')->where('steps.1.status', 'available'));
    }

    public function test_changing_the_focal_issue_after_confirmation_requires_confirming_again(): void
    {
        $project = $this->project($this->manager);
        $this->actingAs($this->manager)->post(route('scenario-planning.confirm', $project));

        // An optional field does not change what the exercise is about.
        $this->actingAs($this->manager)
            ->put(route('scenario-planning.update', $project), $this->payload(['background' => 'Konteks tambahan.']))
            ->assertSessionHasNoErrors();
        $this->assertNotNull($project->fresh()->confirmed_at);

        $this->actingAs($this->manager)
            ->put(route('scenario-planning.update', $project), $this->payload(['target_year' => now()->year + 9]))
            ->assertSessionHas('success');
        $project->refresh();
        $this->assertNull($project->confirmed_at);
        $this->assertNull($project->confirmed_by);
        $this->assertSame('draft', $project->status);
    }

    public function test_only_creator_owner_or_admin_may_change_a_project(): void
    {
        $project = $this->project($this->manager);
        $colleague = User::factory()->create();
        $colleague->givePermissionTo(['view-scenario', 'manage-scenario']);

        $this->actingAs($colleague)->put(route('scenario-planning.update', $project), $this->payload(['name' => 'Diambil alih']))->assertForbidden();
        $this->actingAs($colleague)->post(route('scenario-planning.confirm', $project))->assertForbidden();
        $this->actingAs($colleague)->delete(route('scenario-planning.destroy', $project))->assertForbidden();

        $this->actingAs($this->admin)->put(route('scenario-planning.update', $project), $this->payload(['name' => 'Diperbarui admin']))->assertSessionHasNoErrors();
        $this->assertSame('Diperbarui admin', $project->fresh()->name);
    }

    /**
     * Product integrity PI-01: the exercise status changes only through `scenario-planning.status`
     * (monitoring needs step 9 done; draft only by withdrawing G1). The edit form cannot set it.
     */
    public function test_the_edit_form_cannot_change_the_exercise_status(): void
    {
        $project = $this->project($this->manager);
        $this->actingAs($this->manager)->post(route('scenario-planning.confirm', $project))->assertSessionHasNoErrors();
        $this->assertSame('active', $project->fresh()->status);

        foreach (['monitoring', 'archived', 'draft'] as $status) {
            $this->actingAs($this->manager)
                ->put(route('scenario-planning.update', $project), $this->payload(['status' => $status, 'background' => "Coba {$status}."]))
                ->assertSessionHasNoErrors();
            $this->assertSame('active', $project->fresh()->status, "PUT with status={$status} must not change the status");
        }
        $this->assertSame('Coba draft.', $project->fresh()->background);
    }

    public function test_deleting_a_project_soft_deletes_it(): void
    {
        $project = $this->project($this->manager);

        $this->actingAs($this->manager)->delete(route('scenario-planning.destroy', $project))->assertRedirect(route('scenario-planning.index'));

        $this->assertSoftDeleted($project);
        $this->actingAs($this->manager)->get(route('scenario-planning.show', $project))->assertNotFound();
    }
}
