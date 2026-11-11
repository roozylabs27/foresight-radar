<?php

namespace App\Http\Controllers;

use App\Models\EarlyWarningIndicator;
use App\Models\IndicatorObservation;
use App\Models\ScenarioPlanningProject;
use App\Models\Signal;
use App\Services\IndicatorThresholdService;
use App\Services\SignalMatchService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Observations of an early warning indicator (roadmap R2.3). Whoever may give input on the
 * exercise records them (see ScenarioPlanningProject::acceptsInputFrom); the recorder or the
 * coordinator deletes one. Whether an observation crosses the threshold is decided on the server
 * (audit U4, IndicatorThresholdService): by the indicator's numeric rule, or, for a qualitative
 * indicator only, by a person's flag with a note. A crossing flags the framework, its scenarios and
 * the strategic responses for review.
 */
class IndicatorObservationController extends Controller
{
    public function __construct(
        protected IndicatorThresholdService $thresholds,
    ) {}

    public function store(Request $request, ScenarioPlanningProject $project, EarlyWarningIndicator $indicator): RedirectResponse
    {
        $validated = $request->validate([
            'observed_at' => ['required', 'date', 'before_or_equal:today'],
            'value_numeric' => ['nullable', 'numeric', 'required_without:value_text'],
            'value_text' => ['nullable', 'string', 'max:255'],
            'direction' => ['nullable', Rule::in(IndicatorObservation::DIRECTIONS)],
            // Counts only for an indicator without a numeric threshold, and then needs a note.
            'crossed_threshold' => ['boolean'],
            'signal' => ['nullable', 'uuid', TenantRule::exists('signals', 'uuid')->where('review_status', 'ACCEPTED')],
            'note' => ['nullable', 'string', 'max:1000'],
        ], ['value_numeric.required_without' => 'Isi nilai angka atau keterangan.', 'signal.exists' => 'Pilih sinyal yang sudah diterima.'], [
            'observed_at' => 'tanggal observasi', 'value_numeric' => 'nilai', 'value_text' => 'keterangan',
            'direction' => 'arah', 'signal' => 'sinyal', 'note' => 'catatan',
        ]);
        abort_unless($project->acceptsInputFrom($request->user()), 403);
        $manualFlag = ! $this->thresholds->decidesNumerically($indicator) && (bool) ($validated['crossed_threshold'] ?? false);
        if ($manualFlag && blank($validated['note'] ?? null)) {
            throw ValidationException::withMessages(['note' => 'Jelaskan di catatan mengapa ambang indikator kualitatif ini terlewati.']);
        }

        $observation = $indicator->observations()->create([
            'observed_at' => $validated['observed_at'],
            'value_numeric' => $validated['value_numeric'] ?? null,
            'value_text' => $validated['value_text'] ?? null,
            'direction' => $validated['direction'] ?? null,
            'crossed_threshold' => false,
            'review_status' => 'accepted',
            'signal_id' => empty($validated['signal']) ? null : Signal::where('uuid', $validated['signal'])->value('id'),
            'note' => $validated['note'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        $crossed = $this->thresholds->evaluate($observation, $manualFlag);

        return back()->with('success', $crossed
            ? 'Observasi dicatat. Ambang batas terlewati: kerangka, skenario dan respons strategis ditandai untuk ditinjau.'
            : 'Observasi dicatat.');
    }

    /** Roadmap R2.6: confirm an AI-matched observation; from now on it counts as the confirmer's. */
    public function confirm(Request $request, ScenarioPlanningProject $project, EarlyWarningIndicator $indicator, IndicatorObservation $observation, SignalMatchService $matches): RedirectResponse
    {
        abort_unless($project->acceptsInputFrom($request->user()), 403);

        $matches->confirm($observation, $request->user());
        $crossed = $this->thresholds->evaluate($observation->refresh());

        return back()->with('success', $crossed ? 'Observasi dikonfirmasi. Ambang batas terlewati: kerangka, skenario dan respons strategis ditandai untuk ditinjau.' : 'Observasi dikonfirmasi.');
    }

    public function destroy(Request $request, ScenarioPlanningProject $project, EarlyWarningIndicator $indicator, IndicatorObservation $observation): RedirectResponse
    {
        abort_unless($observation->recorded_by === $request->user()->id || $request->user()->can('update', $project), 403);

        DB::transaction(function () use ($observation) {
            $this->thresholds->close($observation);
            $observation->delete();
        });

        return back()->with('success', 'Observasi dihapus.');
    }
}
