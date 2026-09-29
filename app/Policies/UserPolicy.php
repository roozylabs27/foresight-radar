<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $authUser, User $targetUser): bool
    {
        // Cannot edit users with higher-privilege roles
        if ($targetUser->hasRole('developer') && !$authUser->hasRole('developer')) {
            return false;
        }
        if ($targetUser->hasRole('super-admin') && !$authUser->hasAnyRole(['developer', 'super-admin'])) {
            return false;
        }
        return $authUser->hasAnyRole(['developer', 'super-admin', 'admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $authUser, User $targetUser): bool
    {
        // Cannot delete yourself
        if ($authUser->id === $targetUser->id) {
            return false;
        }
        return $this->update($authUser, $targetUser);
    }
}
