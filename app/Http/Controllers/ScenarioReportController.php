<?php

namespace App\Http\Controllers;

use App\Models\ScenarioPlanningProject;
use App\Services\ScenarioReportService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roadmap R3.5: printable report of one exercise (browser "Save as PDF"). Needs `view-scenario`.
 */
class ScenarioReportController extends Controller
{
    public function show(ScenarioPlanningProject $project, ScenarioReportService $reports): Response
    {
        return Inertia::render('ScenarioPlanning/Report', ['title' => 'Laporan · '.$project->name] + $reports->build($project));
    }
}
