<?php

namespace App\Policies;

use App\Models\Scenario;
use App\Models\User;

/**
 * Who may change a scenario: its creator or an admin role. An exercise framework belongs to the
 * exercise, so the exercise's rule applies (creator, owner, admin: ScenarioPlanningProjectPolicy),
 * on the exercise's Step 6 page and on the older Scenarios page alike (audit U3, AUD-02).
 */
class ScenarioPolicy
{
    public function update(User $user, Scenario $scenario): bool
    {
        if ($scenario->project) {
            return $user->can('update', $scenario->project);
        }

        return $user->id === $scenario->created_by
            || $user->hasAnyRole(['developer', 'super-admin', 'admin']);
    }

    public function delete(User $user, Scenario $scenario): bool
    {
        return $this->update($user, $scenario);
    }
}
