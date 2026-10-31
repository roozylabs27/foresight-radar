<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScenarioPlanningProjectRequest;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use App\Services\ScenarioPlanningProjectService;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Scenario planning exercises (roadmap R0.1). The project workspace grows a
 * screen per method step in later roadmap items.
 */
class ScenarioPlanningProjectController extends Controller
{
    public function __construct(
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request): Response
    {
        $projects = ScenarioPlanningProject::with('owner:id,uuid,name')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($project) => $this->projects->present($project));

        return Inertia::render('ScenarioPlanning/Index', [
            'title' => 'Scenario Planning',
            'projects' => $projects,
            'canManage' => Gate::allows('manage-scenario'),
            'owners' => $this->owners($request->user()),
        ]);
    }

    public function show(Request $request, ScenarioPlanningProject $project): Response
    {
        return Inertia::render('ScenarioPlanning/Show', [
            'title' => $project->name,
            'project' => $this->projects->present($project),
            'steps' => $this->projects->steps($project),
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
            'owners' => $this->owners($request->user()),
        ]);
    }

    public function store(ScenarioPlanningProjectRequest $request): RedirectResponse
    {
        $project = $this->projects->create($request->validated(), $request->user());

        return to_route('scenario-planning.show', $project)->with('success', 'Latihan scenario planning dibuat. Konfirmasi focal issue untuk membuka langkah berikutnya.');
    }

    public function update(ScenarioPlanningProjectRequest $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $reconfirm = $this->projects->update($project, $request->validated(), $request->user());

        return back()->with('success', $reconfirm
            ? 'Focal issue diubah setelah dikonfirmasi. Konfirmasi ulang sebelum melanjutkan langkah berikutnya.'
            : 'Latihan disimpan.');
    }

    public function confirm(Request $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('confirm', $project);

        $this->projects->confirm($project, $request->user());

        return back()->with('success', 'Focal issue dikonfirmasi. Langkah 2–9 sekarang terbuka.');
    }

    public function destroy(ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return to_route('scenario-planning.index')->with('success', 'Latihan dihapus.');
    }

    /**
     * Members of the tenant in context (an operator's picked workspace included) who can own an exercise.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function owners(User $user): array
    {
        $tenantId = app(TenantContext::class)->getTenantId() ?? $user->tenant_id;

        return User::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['uuid', 'name'])
            ->map(fn ($member) => ['value' => $member->uuid, 'label' => $member->name])
            ->all();
    }
}
