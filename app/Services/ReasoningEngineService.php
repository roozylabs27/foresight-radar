<?php

namespace App\Services;

use App\Enums\PriorityLevel;
use App\Enums\StatusActionType;
use App\Enums\TimeHorizonType;
use App\Models\Dimension;
use App\Models\DrivingForceRating;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Rule-based reasoning over approved driving force ratings: Growth and
 * Resilience indices with per-force contributions, the 2x2 readiness posture
 * and the EDA datasets of the Overview.
 *
 * Both indices are a weighted average on the same 0-100 scale:
 *   index = 50 + Σ(w · d) / Σw
 * where d ∈ [-50, +50] is the score a rule gives one force and w its time
 * horizon weight. A force's contribution is w · d / Σw, so the contributions
 * add up to the index minus the baseline. Weights and thresholds are product
 * decisions documented in docs/product/REASONING_ENGINE.md.
 */
class ReasoningEngineService
{
    public const BASELINE = 50.0;

    /** Posture quadrant thresholds. */
    public const RESILIENCE_THRESHOLD = 60.0;

    public const GROWTH_THRESHOLD = 60.0;

    /** A Monitoring force at or above this impact counts as risk exposure. */
    public const EXPOSURE_IMPACT = 7;

    /** Dimensions whose forces are growth opportunities. */
    public const GROWTH_DIMENSIONS = ['economy', 'technology', 'customer'];

    /** Horizon weights; an unset horizon is weighted as Mid Term. */
    public const RESILIENCE_HORIZON_WEIGHTS = ['ST' => 1.0, 'MT' => 0.75, 'LT' => 0.5];

    public const GROWTH_HORIZON_WEIGHTS = ['ST' => 1.0, 'MT' => 1.0, 'LT' => 1.0];

    /** Months the trend chart covers when the selected period is shorter. */
    public const TREND_MONTHS = 12;

    public function __construct(
        protected ForesightReportingService $reportingService,
        protected RatingHistoryAnalyticsService $historyAnalytics,
    ) {}

    /**
     * Compute full executive reasoning payload.
     */
    public function getExecutiveOverview(?array $dateRange = null, mixed $dimension = null): array
    {
        $ratings = $this->loadRatings($dateRange, $dimension);

        $resilience = $this->explainResilienceIndex($ratings);
        $growth = $this->explainGrowthIndex($ratings);
        $readiness = $this->determineReadinessStatus($resilience['index'], $growth['index']);
        $trend = $this->calculateEdaTrend($dateRange, $dimension);

        return [
            'growth_index' => $growth['index'],
            'resilience_index' => $resilience['index'],
            'readiness_status' => $readiness['status'],
            'readiness_badge' => $readiness['badge'],
            'readiness_color' => $readiness['color'],
            'readiness_description' => $readiness['description'],
            'total_active_forces' => $ratings->count(),
            'top_critical_forces' => $this->extractTopCriticalForces($ratings),
            'explanation' => [
                'baseline' => self::BASELINE,
                'thresholds' => [
                    'resilience' => self::RESILIENCE_THRESHOLD,
                    'growth' => self::GROWTH_THRESHOLD,
                ],
                'posture_rule' => $readiness['rule'],
                'growth' => $growth,
                'resilience' => $resilience,
            ],
            'eda_frequency' => $this->calculateEdaFrequency($ratings),
            'eda_distribution' => $this->calculateEdaDistribution($ratings),
            'eda_relationship' => $this->calculateEdaRelationship($ratings),
            'eda_comparison' => $this->calculateEdaComparison($ratings),
            'eda_trend' => $trend['data'],
            'eda_trend_range' => $trend['range'],
            'history_trend' => $this->historyAnalytics->scoreTrend($trend['range'], $dimension),
            'transition_velocity' => $this->historyAnalytics->transitionVelocity($trend['range'], $dimension),
        ];
    }

    /**
     * Ratings the indices are computed from, with the relations they read.
     */
    public function loadRatings(?array $dateRange = null, mixed $dimension = null): Collection
    {
        return $this->reportingService->baseApprovedRatingQuery($dateRange, $dimension)
            ->with([
                'driving_force.dimension',
                'priority',
                'status_action',
                'time_horizon',
            ])
            ->get();
    }

    public function calculateResilienceIndex(Collection $ratings): float
    {
        return $this->explainResilienceIndex($ratings)['index'];
    }

    public function calculateGrowthIndex(Collection $ratings): float
    {
        return $this->explainGrowthIndex($ratings)['index'];
    }

    /**
     * Resilience Index over all forces:
     * - Decided Plan (planned mitigation): d = +50 · I · (1 − U/2)
     * - Monitoring with impact ≥ 7 (risk exposure): d = −50 · I · U
     * - Monitoring with lower impact: d = 0, but still weighs on the average
     */
    public function explainResilienceIndex(Collection $ratings): array
    {
        return $this->explainIndex($ratings, self::RESILIENCE_HORIZON_WEIGHTS, function (DrivingForceRating $rating, array $s) {
            $impactText = "impact {$s['impact']}";

            return match (true) {
                $s['action'] === StatusActionType::DECIDED_PLAN => [
                    50 * $s['i'] * (1 - $s['u'] / 2),
                    "Decided Plan, {$impactText}, uncertainty {$s['uncertainty']} → mitigasi terencana",
                ],
                $s['action'] === StatusActionType::MONITORING && $s['impact'] >= self::EXPOSURE_IMPACT => [
                    -50 * $s['i'] * $s['u'],
                    "Monitoring, {$impactText} ≥ ".self::EXPOSURE_IMPACT.", uncertainty {$s['uncertainty']} → eksposur risiko",
                ],
                $s['action'] === StatusActionType::MONITORING => [
                    0.0,
                    "Monitoring, {$impactText} < ".self::EXPOSURE_IMPACT.' → netral',
                ],
                default => [0.0, 'Status aksi tidak dikenali → netral'],
            };
        });
    }

    /**
     * Growth Index over forces in the opportunity dimensions:
     * - Decided Plan (opportunity seized): d = +50 · I · (1 − U)
     * - Monitoring (opportunity not yet seized): d = −25 · I · U
     */
    public function explainGrowthIndex(Collection $ratings): array
    {
        $opportunities = $ratings->filter(function ($rating) {
            $name = strtolower($rating->driving_force?->dimension?->name ?? '');

            return in_array($name, self::GROWTH_DIMENSIONS, true);
        });

        return $this->explainIndex($opportunities, self::GROWTH_HORIZON_WEIGHTS, function (DrivingForceRating $rating, array $s) {
            $dimension = $rating->driving_force?->dimension?->name;
            $scores = "impact {$s['impact']}, uncertainty {$s['uncertainty']}";

            return match ($s['action']) {
                StatusActionType::DECIDED_PLAN => [
                    50 * $s['i'] * (1 - $s['u']),
                    "Decided Plan di {$dimension}, {$scores} → peluang ditangkap",
                ],
                StatusActionType::MONITORING => [
                    -25 * $s['i'] * $s['u'],
                    "Monitoring di {$dimension}, {$scores} → peluang belum ditangkap",
                ],
                default => [0.0, 'Status aksi tidak dikenali → netral'],
            };
        });
    }

    /**
     * Weighted average of rule scores around the baseline, with each force's
     * contribution, largest first.
     *
     * @param  callable(DrivingForceRating, array): array{0: float, 1: string}  $rule
     */
    protected function explainIndex(Collection $ratings, array $horizonWeights, callable $rule): array
    {
        $rows = $ratings->map(function (DrivingForceRating $rating) use ($horizonWeights, $rule) {
            $impact = $this->score($rating->impact_analysis);
            $uncertainty = $this->score($rating->uncertainty_analysis);
            $horizon = TimeHorizonType::fromModel($rating->time_horizon);
            $state = [
                'impact' => $impact,
                'uncertainty' => $uncertainty,
                'i' => $impact / 10,
                'u' => ($uncertainty - 1) / 9,
                'action' => StatusActionType::fromModel($rating->status_action),
            ];
            [$score, $text] = $rule($rating, $state);

            return [
                'rating' => $rating,
                'horizon' => $horizon,
                'weight' => $horizonWeights[($horizon ?? TimeHorizonType::MID_TERM)->value],
                'score' => $score,
                'rule' => $text,
                'impact' => $impact,
                'uncertainty' => $uncertainty,
                'action' => $state['action'],
            ];
        })->values();

        $totalWeight = $rows->sum('weight');
        if ($totalWeight <= 0) {
            return [
                'index' => self::BASELINE,
                'baseline' => self::BASELINE,
                'total_weight' => 0.0,
                'contributions' => [],
            ];
        }

        $contributions = $rows->map(function (array $row) use ($totalWeight) {
            $rating = $row['rating'];
            $contribution = $row['weight'] * $row['score'] / $totalWeight;

            return [
                'id' => $rating->driving_force?->uuid,
                'keyword' => $rating->driving_force?->keyword,
                'dimension' => $rating->driving_force?->dimension?->name,
                'status_action' => $row['action']?->label(),
                'time_horizon' => $row['horizon']?->label(),
                'impact' => $row['impact'],
                'uncertainty' => $row['uncertainty'],
                'weight' => $row['weight'],
                'score' => round($row['score'], 2),
                'contribution' => round($contribution, 2),
                'raw_contribution' => $contribution,
                'effect' => $contribution > 0.0 ? 'positive' : ($contribution < 0.0 ? 'negative' : 'neutral'),
                'rule' => $row['rule'],
            ];
        });

        $index = round(self::BASELINE + $contributions->sum('raw_contribution'), 1);

        return [
            'index' => $index,
            'baseline' => self::BASELINE,
            'total_weight' => round($totalWeight, 2),
            'contributions' => $contributions
                ->sort(fn ($a, $b) => abs($b['raw_contribution']) <=> abs($a['raw_contribution'])
                    ?: strcmp((string) $a['keyword'], (string) $b['keyword']))
                ->map(fn ($row) => collect($row)->except('raw_contribution')->all())
                ->values()
                ->all(),
        ];
    }

    /**
     * Score on the 1-10 rubric; an unrated score counts as the midpoint 5.
     */
    protected function score(mixed $value): int
    {
        return $value === null ? 5 : max(1, min(10, (int) $value));
    }

    /**
     * Determine organizational posture status based on R and G.
     */
    public function determineReadinessStatus(float $resilience, float $growth): array
    {
        $resilient = $resilience >= self::RESILIENCE_THRESHOLD;
        $growing = $growth >= self::GROWTH_THRESHOLD;

        $posture = match (true) {
            $resilient && $growing => [
                'status' => 'Agile & Resilient',
                'badge' => 'Strong',
                'color' => 'success',
                'description' => 'Organisasi memiliki mitigasi risiko terencana yang kuat dan aktif memanfaatkan peluang pertumbuhan pasar.',
            ],
            $resilient => [
                'status' => 'Defensive Prepared',
                'badge' => 'Moderate',
                'color' => 'processing',
                'description' => 'Ketahanan terhadap ancaman eksternal solid, namun inisiatif penangkapan peluang pertumbuhan masih terbatas.',
            ],
            $growing => [
                'status' => 'Growth-Aggressive (Exposed)',
                'badge' => 'Attention',
                'color' => 'warning',
                'description' => 'Inisiatif pertumbuhan agresif, namun terdapat eksposur risiko signifikan pada forces yang belum termitigasi.',
            ],
            default => [
                'status' => 'Vulnerable (Needs Attention)',
                'badge' => 'Action Needed',
                'color' => 'error',
                'description' => 'Diperlukan tindakan segera untuk memitigasi forces berdampak tinggi yang masih dalam status monitoring.',
            ],
        };

        $posture['rule'] = sprintf(
            'Resilience %s %s %s dan Growth %s %s %s → %s',
            $this->decimal($resilience),
            $resilient ? '≥' : '<',
            $this->decimal(self::RESILIENCE_THRESHOLD, 0),
            $this->decimal($growth),
            $growing ? '≥' : '<',
            $this->decimal(self::GROWTH_THRESHOLD, 0),
            $posture['status'],
        );

        return $posture;
    }

    protected function decimal(float $value, int $decimals = 1): string
    {
        return number_format($value, $decimals, ',', '.');
    }

    /**
     * Extract Top 3 Critical Driving Forces requiring leadership attention.
     */
    public function extractTopCriticalForces(Collection $ratings): array
    {
        return $ratings
            ->sortByDesc(function ($rating) {
                $impact = (int) ($rating->impact_analysis ?? 0);
                $isMonitoring = StatusActionType::fromModel($rating->status_action) === StatusActionType::MONITORING ? 10 : 0;
                $isHighPriority = PriorityLevel::fromModel($rating->priority) === PriorityLevel::HIGH ? 5 : 0;

                return $impact + $isMonitoring + $isHighPriority;
            })
            ->take(3)
            ->map(function ($rating) {
                return [
                    'id' => $rating->driving_force?->id,
                    'uuid' => $rating->driving_force?->uuid,
                    'name' => $rating->driving_force?->keyword,
                    'keyword' => $rating->driving_force?->keyword,
                    'description' => $rating->driving_force?->description,
                    'dimension' => $rating->driving_force?->dimension?->name,
                    'impact' => $rating->impact_analysis,
                    'uncertainty' => $rating->uncertainty_analysis,
                    'priority' => $rating->priority?->name ?? 'Low',
                    'priority_color' => $rating->priority?->color ?? 'green',
                    'status_action' => $rating->status_action?->name ?? 'Monitoring',
                    'status_action_code' => $rating->status_action?->code ?? StatusActionType::MONITORING->value,
                    'time_horizon' => $rating->time_horizon?->name ?? 'Short Term',
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * EDA frequency: forces per dimension, split by status action.
     */
    public function calculateEdaFrequency(Collection $ratings): array
    {
        $countsByDimension = $ratings->groupBy(fn ($rating) => $rating->driving_force?->dimension_id);

        return Dimension::orderBy('id')->get()->map(function ($dim) use ($countsByDimension) {
            $group = $countsByDimension->get($dim->id, collect());

            return [
                'dimension_id' => $dim->id,
                'name' => $dim->name,
                'count' => $group->count(),
                'decided_count' => $this->countAction($group, StatusActionType::DECIDED_PLAN),
                'monitoring_count' => $this->countAction($group, StatusActionType::MONITORING),
            ];
        })->toArray();
    }

    protected function countAction(Collection $ratings, StatusActionType $action): int
    {
        return $ratings->filter(fn ($rating) => StatusActionType::fromModel($rating->status_action) === $action)->count();
    }

    /**
     * EDA distribution: how many forces got each impact and uncertainty score
     * (1-10), plus summary statistics. Unrated scores are left out.
     */
    public function calculateEdaDistribution(Collection $ratings): array
    {
        $impacts = $this->rated($ratings, 'impact_analysis');
        $uncertainties = $this->rated($ratings, 'uncertainty_analysis');
        $impactCounts = array_count_values($impacts);
        $uncertaintyCounts = array_count_values($uncertainties);

        return [
            'bins' => array_map(fn ($score) => [
                'score' => $score,
                'impact' => $impactCounts[$score] ?? 0,
                'uncertainty' => $uncertaintyCounts[$score] ?? 0,
            ], range(1, 10)),
            'impact' => $this->summary($impacts),
            'uncertainty' => $this->summary($uncertainties),
        ];
    }

    /**
     * EDA relationship: impact × uncertainty points and their Pearson
     * correlation (null with fewer than two points or no variance).
     */
    public function calculateEdaRelationship(Collection $ratings): array
    {
        $points = $ratings
            ->filter(fn ($rating) => $rating->impact_analysis !== null && $rating->uncertainty_analysis !== null)
            ->map(fn ($rating) => [
                'id' => $rating->driving_force?->uuid,
                'keyword' => $rating->driving_force?->keyword,
                'dimension' => $rating->driving_force?->dimension?->name,
                'status_action' => StatusActionType::fromModel($rating->status_action)?->label(),
                'impact' => (int) $rating->impact_analysis,
                'uncertainty' => (int) $rating->uncertainty_analysis,
            ])
            ->values();

        $r = $this->pearson($points->pluck('impact')->all(), $points->pluck('uncertainty')->all());

        return [
            'points' => $points->all(),
            'n' => $points->count(),
            'pearson_r' => $r === null ? null : round($r, 3),
            'strength' => $this->correlationStrength($r),
        ];
    }

    /**
     * EDA comparison: spread of impact per dimension and per time horizon.
     */
    public function calculateEdaComparison(Collection $ratings): array
    {
        $byDimension = $ratings->groupBy(fn ($rating) => $rating->driving_force?->dimension?->name ?? 'Tanpa Dimensi');
        $byHorizon = $ratings->groupBy(fn ($rating) => TimeHorizonType::fromModel($rating->time_horizon)?->label() ?? 'Tanpa Horizon');

        $dimensions = Dimension::orderBy('id')->pluck('name')
            ->filter(fn ($name) => $byDimension->has($name))
            ->map(fn ($name) => $this->groupSpread($name, $byDimension->get($name)))
            ->values()
            ->all();

        $horizons = collect(TimeHorizonType::cases())
            ->map(fn ($horizon) => $horizon->label())
            ->push('Tanpa Horizon')
            ->filter(fn ($label) => $byHorizon->has($label))
            ->map(fn ($label) => $this->groupSpread($label, $byHorizon->get($label)))
            ->values()
            ->all();

        return ['by_dimension' => $dimensions, 'by_horizon' => $horizons];
    }

    protected function groupSpread(string $name, Collection $ratings): array
    {
        return ['name' => $name, 'count' => $ratings->count()] + $this->summary($this->rated($ratings, 'impact_analysis'));
    }

    /**
     * EDA trend: forces per month over the selected period, widened to the
     * last TREND_MONTHS months up to its end. Empty months are kept so the
     * chart has a continuous axis.
     */
    public function calculateEdaTrend(?array $dateRange = null, mixed $dimension = null): array
    {
        [$selectedStart, $selectedEnd] = $this->reportingService->resolveDateRange($dateRange);
        $end = Carbon::parse($selectedEnd)->endOfDay();
        $start = Carbon::parse($selectedStart)->startOfMonth();
        $windowStart = $end->copy()->startOfMonth()->subMonths(self::TREND_MONTHS - 1);
        if ($windowStart->lt($start)) {
            $start = $windowStart;
        }

        $range = [$start->format('Y-m-d'), $end->format('Y-m-d')];
        $byMonth = $this->reportingService->baseApprovedRatingQuery($range, $dimension)
            ->with('status_action')
            ->get()
            ->groupBy(fn ($rating) => Carbon::parse($rating->created_at)->format('Y-m'));

        $data = [];
        for ($month = $start->copy(); $month->lte($end); $month->addMonth()) {
            $group = $byMonth->get($month->format('Y-m'), collect());
            $impact = $group->whereNotNull('impact_analysis')->avg('impact_analysis');
            $uncertainty = $group->whereNotNull('uncertainty_analysis')->avg('uncertainty_analysis');
            $data[] = [
                'period' => $month->format('Y-m'),
                'total' => $group->count(),
                'decided_plan' => $this->countAction($group, StatusActionType::DECIDED_PLAN),
                'monitoring' => $this->countAction($group, StatusActionType::MONITORING),
                'avg_impact' => $impact === null ? null : round($impact, 1),
                'avg_uncertainty' => $uncertainty === null ? null : round($uncertainty, 1),
            ];
        }

        return ['data' => $data, 'range' => $range];
    }

    /**
     * @return array<int, int>
     */
    protected function rated(Collection $ratings, string $column): array
    {
        return $ratings->pluck($column)
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();
    }

    /**
     * Five-number summary and mean; quartiles use linear interpolation.
     */
    protected function summary(array $values): array
    {
        if ($values === []) {
            return ['min' => null, 'q1' => null, 'median' => null, 'q3' => null, 'max' => null, 'mean' => null];
        }

        sort($values);

        return [
            'min' => $values[0],
            'q1' => round($this->quantile($values, 0.25), 2),
            'median' => round($this->quantile($values, 0.5), 2),
            'q3' => round($this->quantile($values, 0.75), 2),
            'max' => $values[count($values) - 1],
            'mean' => round(array_sum($values) / count($values), 2),
        ];
    }

    protected function quantile(array $sorted, float $q): float
    {
        $position = (count($sorted) - 1) * $q;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);

        return $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($position - $lower);
    }

    protected function pearson(array $x, array $y): ?float
    {
        $n = count($x);
        if ($n < 2) {
            return null;
        }

        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;
        $covariance = 0.0;
        $varianceX = 0.0;
        $varianceY = 0.0;
        for ($k = 0; $k < $n; $k++) {
            $dx = $x[$k] - $meanX;
            $dy = $y[$k] - $meanY;
            $covariance += $dx * $dy;
            $varianceX += $dx * $dx;
            $varianceY += $dy * $dy;
        }

        if ($varianceX == 0.0 || $varianceY == 0.0) {
            return null;
        }

        return $covariance / sqrt($varianceX * $varianceY);
    }

    protected function correlationStrength(?float $r): ?string
    {
        if ($r === null) {
            return null;
        }

        $size = abs($r);
        $strength = $size < 0.3 ? 'lemah' : ($size < 0.7 ? 'sedang' : 'kuat');
        $direction = $r > 0 ? 'positif' : ($r < 0 ? 'negatif' : '');

        return trim("{$strength} {$direction}");
    }
}
