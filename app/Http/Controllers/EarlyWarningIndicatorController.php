<?php

namespace App\Http\Controllers;

use App\Http\Requests\EarlyWarningIndicatorRequest;
use App\Models\EarlyWarningIndicator;
use App\Models\ScenarioPlanningProject;
use App\Services\EarlyWarningIndicatorService;
use App\Services\ExternalIndicatorService;
use App\Services\IndicatorSuggestionService;
use App\Services\ScenarioPlanningProjectService;
use App\Services\SignalMatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step 9: early warning indicator register (roadmap R1.9). Changes need `manage-scenario` and the
 * project policy (creator, owner, admin).
 */
class EarlyWarningIndicatorController extends Controller
{
    public function __construct(
        protected EarlyWarningIndicatorService $indicators,
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request, ScenarioPlanningProject $project): Response
    {
        $framework = $project->framework()->first();

        return Inertia::render('ScenarioPlanning/Indicators', [
            'title' => 'Early Warning · '.$project->name,
            'project' => $this->projects->present($project),
            'steps' => $this->projects->steps($project),
            'frameworkApproved' => $framework?->isFrameworkApproved() ?? false,
            'quadrants' => $framework ? $this->indicators->quadrants($framework) : [],
            'indicators' => $this->indicators->list($project),
            'relevance' => $framework ? $this->indicators->relevance($project, $framework) : null,
            // PI-09: what still keeps step 9 open (empty when done).
            'stepNineGaps' => $this->indicators->stepNineGaps($project),
            'uncertainties' => $project->criticalUncertainties()->orderBy('name')->get(['uuid', 'name'])->map(fn ($item) => ['id' => $item->uuid, 'name' => $item->name])->all(),
            'forces' => $project->projectForces()->where('inclusion_status', 'included')->with('drivingForce')->get()
                ->map(fn ($link) => ['id' => $link->uuid, 'keyword' => $link->drivingForce?->keyword])->sortBy('keyword')->values()->all(),
            'owners' => $this->projects->owners($request->user()),
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
            'signals' => $this->indicators->signalOptions(),
            'sensing' => $this->indicators->sensing(),
            'canRecord' => $project->recordsObservationsFrom($request->user()),
            'indicatorStatus' => $project->indicator_suggestion_status,
            'indicatorError' => $project->indicator_suggestion_error,
            'matchStatus' => $project->match_suggestion_status,
            'matchError' => $project->match_suggestion_error,
        ]);
    }

    /** Roadmap R2.6: queue AI early warning indicators. */
    public function suggest(Request $request, ScenarioPlanningProject $project, IndicatorSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $suggestions->request($project, $request->user());

        return back()->with('success', 'AI sedang menyusun usulan indikator. Halaman ini diperbarui otomatis.');
    }

    /** Roadmap R2.6: queue AI matching of new accepted signals to the indicators. */
    public function match(Request $request, ScenarioPlanningProject $project, SignalMatchService $matches): RedirectResponse
    {
        $this->authorize('update', $project);

        $matches->request($project, $request->user());

        return back()->with('success', 'AI sedang mencocokkan sinyal baru dengan indikator. Halaman ini diperbarui otomatis.');
    }

    /** Roadmap R3.6: queue a fetch of the indicator's external series. */
    public function fetch(Request $request, ScenarioPlanningProject $project, EarlyWarningIndicator $indicator, ExternalIndicatorService $sources): RedirectResponse
    {
        $this->authorize('update', $project);

        $sources->request($indicator);

        return back()->with('success', 'Data sumber eksternal sedang diambil. Nilai baru masuk sebagai usulan.');
    }

    public function accept(Request $request, ScenarioPlanningProject $project, EarlyWarningIndicator $indicator, IndicatorSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $suggestions->accept($indicator, $request->user());

        return back()->with('success', 'Indikator diterima.');
    }

    public function store(EarlyWarningIndicatorRequest $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->indicators->create($project, $request->validated(), $request->user());

        return back()->with('success', 'Indikator disimpan.');
    }

    public function update(EarlyWarningIndicatorRequest $request, ScenarioPlanningProject $project, EarlyWarningIndicator $indicator): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->indicators->update($indicator, $request->validated(), $request->user());

        return back()->with('success', 'Indikator diperbarui.');
    }

    public function destroy(ScenarioPlanningProject $project, EarlyWarningIndicator $indicator): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->indicators->delete($indicator);

        return back()->with('success', 'Indikator dihapus.');
    }
}
