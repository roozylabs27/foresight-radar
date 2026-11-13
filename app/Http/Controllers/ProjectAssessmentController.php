<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForceAssessmentRequest;
use App\Http\Requests\IndividualAssessmentRequest;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Services\ProjectAssessmentService;
use App\Services\ScenarioPlanningProjectService;
use App\Services\ScoreSuggestionService;
use App\Support\DecisionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step 3 of an exercise: impact and uncertainty scores and gate G3 (roadmap R1.1).
 */
class ProjectAssessmentController extends Controller
{
    public function __construct(
        protected ProjectAssessmentService $assessments,
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request, ScenarioPlanningProject $project): Response
    {
        return Inertia::render('ScenarioPlanning/Assessment', [
            'title' => 'Critical Uncertainties · '.$project->name,
            'project' => $this->projects->present($project),
            'steps' => $this->projects->steps($project),
            'forces' => $forces = $this->assessments->list($project, $request->user()),
            'wideSpread' => ProjectAssessmentService::WIDE_SPREAD,
            // PI-02: shown in the G3 dialog (forces still to review, and with a wide spread to discuss).
            'libraryPending' => count(array_filter($forces, fn ($force) => $force['library_pending'])),
            'spreadCount' => count(array_filter($forces, fn ($force) => $force['individual']['wide'])),
            'canScore' => Gate::allows('view-scenario') && $project->acceptsInputFrom($request->user()),
            'suggestionStatus' => $project->score_suggestion_status,
            'suggestionError' => $project->score_suggestion_error,
            'threshold' => ScenarioPlanningProjectService::HIGH_SCORE,
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
        ]);
    }

    public function update(ForceAssessmentRequest $request, ScenarioPlanningProject $project, ProjectDrivingForce $projectForce): RedirectResponse
    {
        $this->authorize('update', $project);

        $withdrawn = $this->assessments->score($projectForce, $request->validated(), $request->user());

        return back()->with('success', $withdrawn
            ? 'Skor disimpan. Skor berubah, jadi kesepakatan skor perlu diulang.'
            : 'Skor disimpan.');
    }

    /** Roadmap R2.4: queue AI score suggestions for the included forces. */
    public function suggest(Request $request, ScenarioPlanningProject $project, ScoreSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $suggestions->request($project, $request->user());

        return back()->with('success', 'AI sedang menyiapkan saran skor. Halaman ini diperbarui otomatis.');
    }

    public function acceptSuggestion(Request $request, ScenarioPlanningProject $project, ProjectDrivingForce $projectForce, ScoreSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $withdrawn = $suggestions->accept($projectForce, $request->user());

        return back()->with('success', $withdrawn ? 'Saran AI dipakai. Skor berubah, jadi kesepakatan skor perlu diulang.' : 'Saran AI dipakai sebagai skor kesepakatan.');
    }

    public function rejectSuggestion(ScenarioPlanningProject $project, ProjectDrivingForce $projectForce, ScoreSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $suggestions->reject($projectForce);

        return back()->with('success', 'Saran AI dibuang.');
    }

    /** Roadmap R2.1: the signed-in participant's own score. */
    public function mine(IndividualAssessmentRequest $request, ScenarioPlanningProject $project, ProjectDrivingForce $projectForce): RedirectResponse
    {
        abort_unless($project->acceptsInputFrom($request->user()), 403);

        $this->assessments->scoreMine($projectForce, $request->validated(), $request->user());

        return back()->with('success', 'Skor Anda disimpan. Skor kesepakatan tidak berubah.');
    }

    public function withdraw(Request $request, ScenarioPlanningProject $project, ProjectDrivingForce $projectForce): RedirectResponse
    {
        $this->assessments->withdrawMine($projectForce, $request->user());

        return back()->with('success', 'Skor Anda ditarik.');
    }

    /** PI-02: keep the organisation rating as this exercise's score ("Pakai nilai library"). */
    public function keepLibrary(Request $request, ScenarioPlanningProject $project, ProjectDrivingForce $projectForce): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->assessments->keepLibrary($projectForce, $request->user());

        return back()->with('success', 'Nilai library dipakai sebagai skor kesepakatan.');
    }

    public function confirm(Request $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);
        DecisionContext::fromRequest($request);

        $this->assessments->confirm($project, $request->user());

        return back()->with('success', 'Skor disepakati. Langkah berikutnya terbuka.');
    }
}
