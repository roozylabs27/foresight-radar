<?php

namespace App\Observers;

use App\Models\CriticalUncertainty;
use App\Models\ProjectDecision;
use App\Models\Scenario;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\StrategicResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Roadmap R2.2: logs every gate decision (G1–G7) from the column that holds it, so approvals and
 * withdrawals are recorded whichever service changes them. Runs on `updated`, while the original
 * values are still available.
 */
class GateDecisionObserver
{
    /** Project columns holding gates G1–G3. */
    private const PROJECT_GATES = ['G1' => 'confirmed_at', 'G2' => 'forces_confirmed_at', 'G3' => 'scores_confirmed_at'];

    public function updated(Model $model): void
    {
        match (true) {
            $model instanceof ScenarioPlanningProject => $this->project($model),
            $model instanceof CriticalUncertainty => $this->status($model, 'G4', 'status', 'approved', $model->project_id, $model->name),
            $model instanceof Scenario => $model->project_id
                ? $this->status($model, 'G5', 'framework_status', 'approved', $model->project_id, $model->name)
                : null,
            $model instanceof ScenarioQuadrant => $model->scenario?->project_id
                ? $this->status($model, 'G6', 'review_status', 'approved', $model->scenario->project_id, "{$model->position} · {$model->title}")
                : null,
            $model instanceof StrategicResponse => $this->status($model, 'G7', 'status', 'approved', $model->project_id, $model->title),
            default => null,
        };
    }

    private function project(ScenarioPlanningProject $project): void
    {
        foreach (self::PROJECT_GATES as $gate => $column) {
            if (! $project->wasChanged($column)) {
                continue;
            }
            $was = $project->getOriginal($column) !== null;
            $is = $project->getAttribute($column) !== null;
            if ($was !== $is) {
                $this->record($project->tenant_id, $project->id, $gate, $is ? 'approved' : 'withdrawn', $project->name);
            }
        }
    }

    /** A status column: reaching `$approved` approves, leaving it withdraws, `rejected` rejects. */
    private function status(Model $model, string $gate, string $column, string $approved, int $projectId, ?string $subject): void
    {
        if (! $model->wasChanged($column)) {
            return;
        }
        $was = $model->getOriginal($column);
        $is = $model->getAttribute($column);

        $action = match (true) {
            $is === $approved => 'approved',
            $is === 'rejected' => 'rejected',
            $was === $approved => 'withdrawn',
            default => null,
        };
        if ($action) {
            $this->record((int) $model->getAttribute('tenant_id'), $projectId, $gate, $action, $subject);
        }
    }

    private function record(int $tenantId, int $projectId, string $gate, string $action, ?string $subject): void
    {
        ProjectDecision::create([
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
            'gate' => $gate,
            'action' => $action,
            'subject' => $subject === null ? null : Str::limit($subject, 200, ''),
            'actor_id' => Auth::id(),
        ]);
    }
}
