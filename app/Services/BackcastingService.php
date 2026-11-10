<?php

namespace App\Services;

use App\Models\BackcastingMilestone;
use App\Models\EarlyWarningIndicator;
use App\Models\ProjectDecision;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\StrategicResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Roadmap R4.1 (deck slides 2 and 26): the preferred future and the path back to now. The preferred
 * future is one quadrant of the approved framework, chosen with a rationale; choosing or clearing it
 * is logged as decision `PF`. Milestones lie between the current year and the target year and may
 * point at a strategic response that is not rejected and at an indicator of the same exercise.
 */
class BackcastingService
{
    public const DECISION = 'PF';

    public function choose(ScenarioPlanningProject $project, string $quadrantUuid, string $rationale, User $user): void
    {
        $framework = $project->framework()->first();
        if (! $framework?->isFrameworkApproved()) {
            throw ValidationException::withMessages(['backcasting' => 'Setujui kerangka skenario (langkah 4) dulu sebelum memilih masa depan pilihan.']);
        }
        $quadrant = $framework->quadrants()->where('uuid', $quadrantUuid)->first();
        if (! $quadrant instanceof ScenarioQuadrant) {
            throw ValidationException::withMessages(['quadrant' => 'Pilih salah satu dari empat skenario latihan ini.']);
        }

        DB::transaction(function () use ($project, $quadrant, $rationale, $user) {
            $project->forceFill([
                'preferred_quadrant_id' => $quadrant->id,
                'preferred_rationale' => $rationale,
                'preferred_by' => $user->id,
                'preferred_at' => now(),
            ])->save();
            $this->log($project, 'approved', $quadrant->title, $user);
        });
    }

    public function clear(ScenarioPlanningProject $project, User $user): void
    {
        if ($project->preferred_quadrant_id === null) {
            return;
        }
        $title = $project->preferredQuadrant()->value('title');

        DB::transaction(function () use ($project, $title, $user) {
            $project->forceFill(['preferred_quadrant_id' => null, 'preferred_rationale' => null, 'preferred_by' => null, 'preferred_at' => null])->save();
            $this->log($project, 'withdrawn', $title, $user);
        });
    }

    /** Create or replace a milestone from validated input. */
    public function save(ScenarioPlanningProject $project, array $validated, User $user, ?BackcastingMilestone $milestone = null): BackcastingMilestone
    {
        if ($project->preferred_quadrant_id === null) {
            throw ValidationException::withMessages(['backcasting' => 'Pilih masa depan pilihan dulu sebelum menyusun milestone.']);
        }

        $attributes = [
            'year' => (int) $validated['year'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? $milestone->status ?? 'planned',
            'owner_id' => empty($validated['owner']) ? null : User::where('uuid', $validated['owner'])->value('id'),
            'strategic_response_id' => $this->responseId($project, $validated['response'] ?? null),
            'early_warning_indicator_id' => $this->indicatorId($project, $validated['indicator'] ?? null),
        ];

        if ($milestone) {
            $milestone->update($attributes);

            return $milestone;
        }

        return $project->milestones()->create($attributes + ['tenant_id' => $project->tenant_id, 'created_by' => $user->id]);
    }

    /** What the backcasting page and the report show. */
    public function present(ScenarioPlanningProject $project): array
    {
        $preferred = $project->preferredQuadrant()->first();
        $chooser = $project->preferred_by ? User::withTrashed()->find($project->preferred_by) : null;

        return [
            'preferred' => $preferred ? [
                'id' => $preferred->uuid,
                'position' => $preferred->position,
                'title' => $preferred->title,
                'summary' => $preferred->summary,
                'rationale' => $project->preferred_rationale,
                'chosen_by' => $chooser?->name,
                'chosen_at' => $project->preferred_at?->toISOString(),
            ] : null,
            'milestones' => $project->milestones()
                ->with(['owner:id,uuid,name', 'response:id,uuid,title,status', 'indicator:id,uuid,name'])
                ->orderByDesc('year')
                ->orderBy('id')
                ->get()
                ->map(fn (BackcastingMilestone $row) => [
                    'id' => $row->uuid,
                    'year' => $row->year,
                    'title' => $row->title,
                    'description' => $row->description,
                    'status' => $row->status,
                    'owner' => $row->owner ? ['id' => $row->owner->uuid, 'name' => $row->owner->name] : null,
                    'response' => $row->response ? ['id' => $row->response->uuid, 'title' => $row->response->title] : null,
                    'indicator' => $row->indicator ? ['id' => $row->indicator->uuid, 'name' => $row->indicator->name] : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /** Responses and indicators a milestone may point at. */
    public function options(ScenarioPlanningProject $project): array
    {
        return [
            'responses' => $project->strategicResponses()->where('status', '!=', 'rejected')->orderBy('title')->get(['uuid', 'title'])
                ->map(fn ($row) => ['value' => $row->uuid, 'label' => $row->title])->all(),
            'indicators' => $project->indicators()->where('review_status', 'accepted')->orderBy('name')->get(['uuid', 'name'])
                ->map(fn ($row) => ['value' => $row->uuid, 'label' => $row->name])->all(),
        ];
    }

    private function responseId(ScenarioPlanningProject $project, ?string $uuid): ?int
    {
        if (empty($uuid)) {
            return null;
        }
        $response = $project->strategicResponses()->where('uuid', $uuid)->first();
        if (! $response instanceof StrategicResponse || $response->status === 'rejected') {
            throw ValidationException::withMessages(['response' => 'Pilih respons strategis latihan ini yang tidak ditolak.']);
        }

        return $response->id;
    }

    private function indicatorId(ScenarioPlanningProject $project, ?string $uuid): ?int
    {
        if (empty($uuid)) {
            return null;
        }
        $indicator = $project->indicators()->where('uuid', $uuid)->first();
        if (! $indicator instanceof EarlyWarningIndicator) {
            throw ValidationException::withMessages(['indicator' => 'Pilih indikator dari latihan ini.']);
        }

        return $indicator->id;
    }

    private function log(ScenarioPlanningProject $project, string $action, ?string $subject, User $user): void
    {
        ProjectDecision::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'gate' => self::DECISION,
            'action' => $action,
            'subject' => $subject === null ? null : Str::limit($subject, 200, ''),
            'actor_id' => $user->id,
        ]);
    }
}
