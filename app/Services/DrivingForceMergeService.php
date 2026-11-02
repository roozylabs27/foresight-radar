<?php

namespace App\Services;

use App\Models\DrivingForce;
use App\Models\ProjectDrivingForce;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Roadmap R1.3: merge a duplicate driving force into the one that stays. Everything that pointed at
 * the duplicate moves to the survivor: signal links, cross-impacts (pairs the survivor already has
 * keep the survivor's score; self-loops are dropped), legacy scenario axes, and exercise links (taken
 * over when the survivor is not in that exercise yet, otherwise combined). The duplicate becomes a
 * soft-deleted tombstone pointing at the survivor. Affected scenarios are flagged stale, never deleted.
 */
class DrivingForceMergeService
{
    public function __construct(protected StalenessService $staleness) {}

    /**
     * @throws ValidationException when the target is the same force, deleted or itself merged
     */
    public function merge(DrivingForce $source, DrivingForce $target, User $user): void
    {
        if ($source->id === $target->id) {
            throw ValidationException::withMessages(['target' => 'Pilih driving force lain sebagai tujuan penggabungan.']);
        }
        if ($target->trashed() || $target->merged_into_id !== null) {
            throw ValidationException::withMessages(['target' => 'Driving force tujuan sudah dihapus atau digabung.']);
        }

        $reason = "Driving force {$source->keyword} digabung ke {$target->keyword}";

        DB::transaction(function () use ($source, $target, $user, $reason) {
            DrivingForce::whereKey([$source->id, $target->id])->lockForUpdate()->get();

            $this->moveSignals($source, $target);
            $this->moveCrossImpacts($source, $target);
            $this->moveLegacyAxes($source, $target, $reason);
            $this->moveExerciseLinks($source, $target, $reason);

            $source->forceFill(['merged_into_id' => $target->id, 'merged_by' => $user->id, 'merged_at' => now()])->save();
            $source->delete();
        });
    }

    private function moveSignals(DrivingForce $source, DrivingForce $target): void
    {
        $existing = DB::table('signal_driving_force')->where('driving_force_id', $target->id)->pluck('signal_id');
        DB::table('signal_driving_force')->where('driving_force_id', $source->id)->whereIn('signal_id', $existing)->delete();
        DB::table('signal_driving_force')->where('driving_force_id', $source->id)->update(['driving_force_id' => $target->id]);
        DB::table('signals')->where('created_driving_force_id', $source->id)->update(['created_driving_force_id' => $target->id]);
    }

    private function moveCrossImpacts(DrivingForce $source, DrivingForce $target): void
    {
        $pairs = DB::table('cross_impacts')->where(fn ($q) => $q->where('source_driving_force_id', $target->id)->orWhere('target_driving_force_id', $target->id))
            ->get(['source_driving_force_id', 'target_driving_force_id'])
            ->map(fn ($row) => $row->source_driving_force_id.'>'.$row->target_driving_force_id)
            ->flip();

        foreach (DB::table('cross_impacts')->where('source_driving_force_id', $source->id)->orWhere('target_driving_force_id', $source->id)->get() as $row) {
            $from = $row->source_driving_force_id === $source->id ? $target->id : $row->source_driving_force_id;
            $to = $row->target_driving_force_id === $source->id ? $target->id : $row->target_driving_force_id;
            if ($from === $to || $pairs->has($from.'>'.$to)) {
                DB::table('cross_impacts')->where('id', $row->id)->delete();

                continue;
            }
            DB::table('cross_impacts')->where('id', $row->id)->update(['source_driving_force_id' => $from, 'target_driving_force_id' => $to]);
            $pairs->put($from.'>'.$to, 0);
        }
    }

    private function moveLegacyAxes(DrivingForce $source, DrivingForce $target, string $reason): void
    {
        foreach (Scenario::where('axis_a_force_id', $source->id)->orWhere('axis_b_force_id', $source->id)->get() as $scenario) {
            $scenario->forceFill([
                'axis_a_force_id' => $scenario->axis_a_force_id === $source->id ? $target->id : $scenario->axis_a_force_id,
                'axis_b_force_id' => $scenario->axis_b_force_id === $source->id ? $target->id : $scenario->axis_b_force_id,
                'stale_since' => $scenario->stale_since ?? now(),
                'stale_reason' => trim(($scenario->stale_reason ? $scenario->stale_reason.'; ' : '').$reason),
            ])->save();
        }
    }

    private function moveExerciseLinks(DrivingForce $source, DrivingForce $target, string $reason): void
    {
        foreach (ProjectDrivingForce::where('driving_force_id', $source->id)->with('project')->get() as $link) {
            $project = $link->project;
            $survivor = ProjectDrivingForce::where('project_id', $link->project_id)->where('driving_force_id', $target->id)->first();
            if ($project) {
                $this->staleness->markIfAxisForce($project, $link->id, $reason);
            }

            if (! $survivor) {
                // The survivor takes over this exercise link with its scores, notes and memberships.
                $link->update(['driving_force_id' => $target->id]);

                continue;
            }

            // Both forces were in the exercise: everything hanging off the duplicate's link moves to
            // the survivor's link; the survivor's own scores and settings win.
            $members = DB::table('critical_uncertainty_forces')->where('project_driving_force_id', $survivor->id)->pluck('critical_uncertainty_id');
            DB::table('critical_uncertainty_forces')->where('project_driving_force_id', $link->id)->whereIn('critical_uncertainty_id', $members)->delete();
            DB::table('critical_uncertainty_forces')->where('project_driving_force_id', $link->id)->update(['project_driving_force_id' => $survivor->id]);
            foreach (['scenario_conditions', 'implication_links', 'early_warning_indicators'] as $table) {
                DB::table($table)->where('project_driving_force_id', $link->id)->update(['project_driving_force_id' => $survivor->id]);
            }
            $link->delete();
        }
    }
}
