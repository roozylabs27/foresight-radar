<?php

namespace Tests\Support;

use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use App\Support\Provenance;

/**
 * PI-02: G3 needs every score copied from the organisation rating reviewed. Fixtures that agree
 * scores on library copies press "Pakai nilai library" for each one first, over HTTP.
 */
trait KeepsLibraryScores
{
    protected function keepLibraryScores(User $user, ScenarioPlanningProject $project): void
    {
        $pending = ProjectDrivingForce::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->where('inclusion_status', 'included')
            ->whereHas('consensus', fn ($query) => $query->withoutGlobalScopes()->where('origin', Provenance::LIBRARY)->whereNull('updated_by'))
            ->get();

        foreach ($pending as $link) {
            $this->actingAs($user)->post(route('scenario-planning.assessment.library', [$project, $link]))->assertSessionHasNoErrors();
        }
    }
}
