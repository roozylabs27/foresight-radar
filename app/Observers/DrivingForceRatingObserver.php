<?php

namespace App\Observers;

use App\Models\DrivingForceRating;
use App\Models\DrivingForceRatingHistory;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps driving_force_rating_histories: one row when a rating is created and
 * one more each time a tracked score changes.
 */
class DrivingForceRatingObserver
{
    public const TRACKED = [
        'time_horizon_id',
        'status_action_id',
        'priority_id',
        'impact_analysis',
        'uncertainty_analysis',
    ];

    public function created(DrivingForceRating $rating): void
    {
        $this->record($rating, 'created');
    }

    public function updated(DrivingForceRating $rating): void
    {
        if ($rating->wasChanged(self::TRACKED)) {
            $this->record($rating, 'updated');
        }
    }

    protected function record(DrivingForceRating $rating, string $event): void
    {
        DrivingForceRatingHistory::create([
            'driving_force_rating_id' => $rating->id,
            'driving_force_id' => $rating->driving_force_id,
            'event' => $event,
            'changed_by' => Auth::id(),
        ] + $rating->only(self::TRACKED));
    }
}
