<?php

namespace App\Policies;

use App\Models\ScenarioPlanningProject;
use App\Models\User;

/**
 * Route gates (`view-scenario`, `manage-scenario`) decide who may use projects
 * at all; this decides who may change a given one: its creator, its owner, or
 * an admin role, as ScenarioPolicy does for scenarios.
 */
class ScenarioPlanningProjectPolicy
{
    public function update(User $user, ScenarioPlanningProject $project): bool
    {
        return $user->id === $project->created_by
            || $user->id === $project->owner_user_id
            || $user->hasAnyRole(['developer', 'super-admin', 'admin']);
    }

    public function confirm(User $user, ScenarioPlanningProject $project): bool
    {
        return $this->update($user, $project);
    }

    public function delete(User $user, ScenarioPlanningProject $project): bool
    {
        return $this->update($user, $project);
    }
}
