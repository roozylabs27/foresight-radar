<?php

namespace App\Policies;

use App\Models\DrivingForce;
use App\Models\User;

class DrivingForcePolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DrivingForce $drivingForce): bool
    {
        // Creator, assigned PIC, or users with admin/super-admin/developer role can update
        return $user->id === $drivingForce->created_by
            || $user->id === $drivingForce->pic
            || $user->hasAnyRole(['developer', 'super-admin', 'admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DrivingForce $drivingForce): bool
    {
        // Creator or users with admin/super-admin/developer role can delete
        return $user->id === $drivingForce->created_by
            || $user->hasAnyRole(['developer', 'super-admin', 'admin']);
    }
}
