<?php

namespace App\Services;

use App\Models\Dimension;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReasoningEngineService
{
    protected ForesightReportingService $reportingService;

    public function __construct(ForesightReportingService $reportingService)
    {
        $this->reportingService = $reportingService;
    }

    /**
     * Compute full executive reasoning payload.
     */
    public function getExecutiveOverview(?array $dateRange = null, mixed $dimension = null): array
    {
        $ratings = $this->reportingService->baseApprovedRatingQuery($dateRange, $dimension)
            ->with([
                'driving_force.dimension',
                'priority',
                'status_action',
                'time_horizon',
            ])
            ->get();

        $resilienceIndex = $this->calculateResilienceIndex($ratings);
        $growthIndex = $this->calculateGrowthIndex($ratings);
        $readiness = $this->determineReadinessStatus($resilienceIndex, $growthIndex);
        $topCriticalForces = $this->extractTopCriticalForces($ratings);
        $edaFrequency = $this->calculateEdaFrequency($ratings, $dateRange);
        $edaTrend = $this->calculateEdaTrend($ratings, $dateRange);

        return [
            'growth_index' => $growthIndex,
            'resilience_index' => $resilienceIndex,
            'readiness_status' => $readiness['status'],
            'readiness_badge' => $readiness['badge'],
            'readiness_color' => $readiness['color'],
            'readiness_description' => $readiness['description'],
            'total_active_forces' => $ratings->count(),
            'top_critical_forces' => $topCriticalForces,
            'eda_frequency' => $edaFrequency,
            'eda_trend' => $edaTrend,
        ];
    }

    /**
     * Calculate Resilience Index (0.0 - 100.0, baseline 50.0).
     */
    public function calculateResilienceIndex(Collection $ratings): float
    {
        if ($ratings->isEmpty()) {
            return 50.0;
        }

        $deltaSum = 0.0;
        foreach ($ratings as $rating) {
            $impact = (float) ($rating->impact_analysis ?? 5);
            $uncertainty = (float) ($rating->uncertainty_analysis ?? 5);
            $actionName = $rating->status_action?->name ?? '';

            if ($actionName === 'Decided Plan') {
                // Planned mitigation against clear or assessed risk
                $deltaSum += ($impact * (11.0 - $uncertainty)) / 20.0;
            } elseif ($actionName === 'Monitoring' && $impact >= 7) {
                // High impact under monitoring is a risk exposure penalty
                $deltaSum -= ($impact * $uncertainty) / 30.0;
            }
        }

        $score = 50.0 + $deltaSum;
        return round(max(0.0, min(100.0, $score)), 1);
    }

    /**
     * Calculate Growth Index (0.0 - 100.0, baseline 50.0).
     */
    public function calculateGrowthIndex(Collection $ratings): float
    {
        if ($ratings->isEmpty()) {
            return 50.0;
        }

        $opportunityDimensions = ['customer', 'technology', 'economy'];
        $opportunityRatings = $ratings->filter(function ($rating) use ($opportunityDimensions) {
            $dimName = strtolower($rating->driving_force?->dimension?->name ?? '');
            return in_array($dimName, $opportunityDimensions, true);
        });

        if ($opportunityRatings->isEmpty()) {
            return 50.0;
        }

        $nOpt = $opportunityRatings->count();
        $deltaSum = 0.0;

        foreach ($opportunityRatings as $rating) {
            $impact = (float) ($rating->impact_analysis ?? 5);
            $actionName = $rating->status_action?->name ?? '';

            if ($actionName === 'Decided Plan') {
                $deltaSum += ($impact * 1.5) / $nOpt;
            } else {
                $deltaSum += ($impact * 0.5) / $nOpt;
            }
        }

        $score = 50.0 + $deltaSum;
        return round(max(0.0, min(100.0, $score)), 1);
    }

    /**
     * Determine organizational posture status based on R and G.
     */
    public function determineReadinessStatus(float $resilience, float $growth): array
    {
        if ($resilience >= 70.0 && $growth >= 70.0) {
            return [
                'status' => 'Agile & Resilient',
                'badge' => 'Strong',
                'color' => 'success',
                'description' => 'Organisasi memiliki mitigasi risiko terencana yang kuat dan aktif memanfaatkan peluang pertumbuhan pasar.',
            ];
        }

        if ($resilience >= 70.0 && $growth < 70.0) {
            return [
                'status' => 'Defensive Prepared',
                'badge' => 'Moderate',
                'color' => 'processing',
                'description' => 'Ketahanan terhadap ancaman eksternal solid, namun inisiatif penangkapan peluang pertumbuhan masih terbatas.',
            ];
        }

        if ($resilience < 70.0 && $growth >= 70.0) {
            return [
                'status' => 'Growth-Aggressive (Exposed)',
                'badge' => 'Attention',
                'color' => 'warning',
                'description' => 'Inisiatif pertumbuhan agresif, namun terdapat eksposur risiko signifikan pada forces yang belum termitigasi.',
            ];
        }

        return [
            'status' => 'Vulnerable (Needs Attention)',
            'badge' => 'Action Needed',
            'color' => 'error',
            'description' => 'Diperlukan tindakan segera untuk memitigasi forces berdampak tinggi yang masih dalam status monitoring.',
        ];
    }

    /**
     * Extract Top 3 Critical Driving Forces requiring leadership attention.
     */
    public function extractTopCriticalForces(Collection $ratings): array
    {
        return $ratings
            ->sortByDesc(function ($rating) {
                $impact = (int) ($rating->impact_analysis ?? 0);
                $isMonitoring = ($rating->status_action?->name === 'Monitoring') ? 10 : 0;
                $isHighPriority = (strtolower($rating->priority?->name ?? '') === 'high') ? 5 : 0;
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
                    'status_action_code' => $rating->status_action?->code ?? '∆',
                    'time_horizon' => $rating->time_horizon?->name ?? 'Short Term',
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Compute frequency distribution across all standard dimensions.
     */
    public function calculateEdaFrequency(Collection $ratings, ?array $dateRange = null): array
    {
        $allDimensions = Dimension::all();
        $countsByDimension = $ratings->groupBy(function ($rating) {
            return $rating->driving_force?->dimension_id;
        });

        return $allDimensions->map(function ($dim) use ($countsByDimension) {
            $group = $countsByDimension->get($dim->id, collect());
            return [
                'dimension_id' => $dim->id,
                'name' => $dim->name,
                'count' => $group->count(),
                'decided_count' => $group->filter(fn($r) => $r->status_action?->name === 'Decided Plan')->count(),
                'monitoring_count' => $group->filter(fn($r) => $r->status_action?->name === 'Monitoring')->count(),
            ];
        })->toArray();
    }

    /**
     * Compute trend (monthly timeline) of driving forces.
     */
    public function calculateEdaTrend(Collection $ratings, ?array $dateRange = null): array
    {
        if ($ratings->isEmpty()) {
            return [];
        }

        $byMonth = $ratings->groupBy(function ($rating) {
            return Carbon::parse($rating->created_at)->format('Y-m');
        })->sortKeys();

        $result = [];
        foreach ($byMonth as $month => $group) {
            $result[] = [
                'period' => $month,
                'total' => $group->count(),
                'decided_plan' => $group->filter(fn($r) => $r->status_action?->name === 'Decided Plan')->count(),
                'monitoring' => $group->filter(fn($r) => $r->status_action?->name === 'Monitoring')->count(),
                'avg_impact' => round($group->avg('impact_analysis') ?? 0, 1),
                'avg_uncertainty' => round($group->avg('uncertainty_analysis') ?? 0, 1),
            ];
        }

        return $result;
    }
}
