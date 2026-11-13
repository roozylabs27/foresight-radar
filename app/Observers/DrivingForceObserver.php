<?php

namespace App\Observers;

use App\Models\DrivingForce;
use App\Services\StalenessService;

/**
 * Product integrity PI-06: when a firm library force changes while exercises use it, those
 * exercises are told (with a reason), so the library and the exercise do not drift apart silently.
 * A merge flags its own exercises (DrivingForceMergeService), so the merge tombstone is skipped.
 */
class DrivingForceObserver
{
    /** Fields whose change alters what the force means. */
    private const MEANING = ['keyword', 'description', 'dimension_id'];

    public function updated(DrivingForce $force): void
    {
        $reason = match (true) {
            $force->wasChanged('status') && $force->status === 'REJECTED' => 'ditolak di library',
            $force->wasChanged('status') && $force->status === 'CLOSED' => 'ditutup di library',
            $force->wasChanged(self::MEANING) => 'diubah di library',
            default => null,
        };

        if ($reason !== null) {
            app(StalenessService::class)->firmForceChanged($force, "Driving force {$force->keyword} {$reason}");
        }
    }

    public function deleted(DrivingForce $force): void
    {
        if ($force->merged_into_id === null) {
            app(StalenessService::class)->firmForceChanged($force, "Driving force {$force->keyword} dihapus dari library");
        }
    }
}
