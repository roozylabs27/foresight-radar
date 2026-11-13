<?php

namespace App\Policies;

use App\Models\Scenario;
use App\Models\User;

/**
 * Who may change a scenario. An exercise framework belongs to the exercise, so the exercise's rule
 * applies (creator, owner, admin: ScenarioPlanningProjectPolicy), on the exercise's Step 6 page and on
 * the older Scenarios page alike (audit U3, AUD-02).
 *
 * A stand-alone scenario (no exercise) is a read-only archive since PAD-2 (2026-11-13): it was made
 * outside the nine-step method and its gates, so nobody may edit, regenerate, review or delete it.
 */
class ScenarioPolicy
{
    public function update(User $user, Scenario $scenario): bool
    {
        return $scenario->project !== null && $user->can('update', $scenario->project);
    }

    public function delete(User $user, Scenario $scenario): bool
    {
        return $this->update($user, $scenario);
    }
}
