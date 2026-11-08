<?php

namespace App\Http\Controllers;

use App\Models\EarlyWarningIndicator;
use App\Models\IndicatorObservation;
use App\Models\ScenarioPlanningProject;
use App\Models\Signal;
use App\Services\StalenessService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Observations of an early warning indicator (roadmap R2.3). Whoever may give input on the
 * exercise records them (see ScenarioPlanningProject::acceptsInputFrom); the recorder or the
 * coordinator deletes one. Crossing the threshold flags the scenarios for review (R1.10 staleness).
 */
class IndicatorObservationController extends Controller
{
    public function __construct(
        protected StalenessService $staleness,
    ) {}

    public function store(Request $request, ScenarioPlanningProject $project, EarlyWarningIndicator $indicator): RedirectResponse
    {
        $validated = $request->validate([
            'observed_at' => ['required', 'date', 'before_or_equal:today'],
            'value_numeric' => ['nullable', 'numeric', 'required_without:value_text'],
            'value_text' => ['nullable', 'string', 'max:255'],
            'direction' => ['nullable', Rule::in(IndicatorObservation::DIRECTIONS)],
            'crossed_threshold' => ['boolean'],
            'signal' => ['nullable', 'uuid', TenantRule::exists('signals', 'uuid')->where('review_status', 'ACCEPTED')],
            'note' => ['nullable', 'string', 'max:1000'],
        ], ['value_numeric.required_without' => 'Isi nilai angka atau keterangan.', 'signal.exists' => 'Pilih sinyal yang sudah diterima.'], [
            'observed_at' => 'tanggal observasi', 'value_numeric' => 'nilai', 'value_text' => 'keterangan',
            'direction' => 'arah', 'signal' => 'sinyal', 'note' => 'catatan',
        ]);
        abort_unless($project->acceptsInputFrom($request->user()), 403);

        $observation = $indicator->observations()->create([
            'observed_at' => $validated['observed_at'],
            'value_numeric' => $validated['value_numeric'] ?? null,
            'value_text' => $validated['value_text'] ?? null,
            'direction' => $validated['direction'] ?? null,
            'crossed_threshold' => (bool) ($validated['crossed_threshold'] ?? false),
            'signal_id' => empty($validated['signal']) ? null : Signal::where('uuid', $validated['signal'])->value('id'),
            'note' => $validated['note'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        // A crossed threshold means the futures may be shifting: flag the framework and its scenarios for review.
        if ($observation->crossed_threshold) {
            $this->staleness->markFramework($project, "Indikator {$indicator->name} melewati ambang batas ({$observation->observed_at->toDateString()})");
        }

        return back()->with('success', $observation->crossed_threshold
            ? 'Observasi dicatat. Ambang batas terlewati: kerangka dan skenario ditandai untuk ditinjau.'
            : 'Observasi dicatat.');
    }

    public function destroy(Request $request, ScenarioPlanningProject $project, EarlyWarningIndicator $indicator, IndicatorObservation $observation): RedirectResponse
    {
        abort_unless($observation->recorded_by === $request->user()->id || $request->user()->can('update', $project), 403);

        $observation->delete();

        return back()->with('success', 'Observasi dihapus.');
    }
}
