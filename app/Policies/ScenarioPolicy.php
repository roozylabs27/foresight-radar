<?php

namespace App\Policies;

use App\Models\Scenario;
use App\Models\User;

class ScenarioPolicy
{
    /**
     * Determine whether the user can update the scenario.
     */
    public function update(User $user, Scenario $scenario): bool
    {
        return $user->id === $scenario->created_by
            || $user->hasAnyRole(['developer', 'super-admin', 'admin']);
    }

    /**
     * Determine whether the user can delete the scenario.
     */
    public function delete(User $user, Scenario $scenario): bool
    {
        return $user->id === $scenario->created_by
            || $user->hasAnyRole(['developer', 'super-admin', 'admin']);
    }
}
