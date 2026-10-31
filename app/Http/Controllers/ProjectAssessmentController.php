<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForceAssessmentRequest;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Services\ProjectAssessmentService;
use App\Services\ScenarioPlanningProjectService;
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
            'forces' => $this->assessments->list($project),
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

    public function confirm(Request $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->assessments->confirm($project, $request->user());

        return back()->with('success', 'Skor disepakati. Langkah berikutnya terbuka.');
    }
}
