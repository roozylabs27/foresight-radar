<?php

namespace App\Services;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Signal;
use App\Models\Source;

class KnowledgeGraphService
{
    /**
     * Build graph nodes and links connecting Sources -> Signals -> Driving Forces.
     *
     * @return array{
     *     nodes: array<int, array<string, mixed>>,
     *     links: array<int, array<string, mixed>>,
     *     stats: array<string, mixed>,
     *     dimensions: array<int, string>
     * }
     */
    public function graphData(): array
    {
        $drivingForces = DrivingForce::query()
            ->with(['dimension', 'created_by_user'])
            ->whereNull('deleted_at')
            ->get();

        $signals = Signal::query()
            ->with(['dimension', 'source', 'suggested_time_horizon', 'driving_forces'])
            ->get();

        $sources = Source::query()
            ->withCount('signals')
            ->get();

        $nodes = [];
        $links = [];
        $nodeIdSet = [];

        // 1. Driving Force Nodes (Primary Hubs)
        foreach ($drivingForces as $df) {
            $nodeId = 'df-'.$df->uuid;
            $nodeIdSet[$nodeId] = true;

            $nodes[] = [
                'id' => $nodeId,
                'raw_id' => $df->uuid,
                'label' => $df->keyword,
                'description' => $df->description,
                'type' => 'driving_force',
                'dimension' => $df->dimension?->name ?? 'General',
                'status' => $df->status ?? 'ACTIVE',
                'val' => 22,
                'degree' => 0, // Will be calculated after link assembly
            ];
        }

        // 2. Source Document Nodes (Origin Hubs)
        foreach ($sources as $source) {
            $nodeId = 'src-'.$source->uuid;
            $nodeIdSet[$nodeId] = true;

            $nodes[] = [
                'id' => $nodeId,
                'raw_id' => $source->uuid,
                'label' => $source->title,
                'publisher' => $source->publisher,
                'type' => 'source',
                'dimension' => 'Source',
                'signals_count' => $source->signals_count,
                'val' => 14,
                'degree' => 0,
            ];
        }

        // 3. Signal Nodes (Satellite / Evidence Nodes) & Edges
        foreach ($signals as $signal) {
            $signalNodeId = 'sig-'.$signal->uuid;
            $nodeIdSet[$signalNodeId] = true;

            $nodes[] = [
                'id' => $signalNodeId,
                'raw_id' => $signal->uuid,
                'label' => $signal->title,
                'quote' => $signal->evidence_quote,
                'summary' => $signal->summary,
                'type' => 'signal',
                'dimension' => $signal->dimension?->name ?? 'General',
                'confidence' => $signal->confidence_score ?? 0.8,
                'impact' => $signal->preliminary_impact ?? 5,
                'uncertainty' => $signal->preliminary_uncertainty ?? 5,
                'review_status' => $signal->review_status ?? 'PENDING',
                'quote_verified' => (bool) $signal->quote_verified,
                'source_title' => $signal->source?->title,
                'provider' => $signal->extraction_provider ?? 'heuristic',
                'val' => max(6, min(14, (int) round(($signal->confidence_score ?? 0.8) * 12))),
                'degree' => 0,
            ];

            // Edge: Source -> Signal
            if ($signal->source) {
                $sourceNodeId = 'src-'.$signal->source->uuid;
                if (isset($nodeIdSet[$sourceNodeId])) {
                    $links[] = [
                        'source' => $sourceNodeId,
                        'target' => $signalNodeId,
                        'type' => 'source_to_signal',
                        'label' => 'Mengekstrak',
                        'weight' => 1,
                    ];
                }
            }

            // Edges: Signal -> Driving Force (Supporting, Created, or Suggested)
            $linkedDfUuids = [];

            // Directly linked supporting driving forces
            foreach ($signal->driving_forces as $df) {
                $linkedDfUuids[] = $df->uuid;
            }

            // Created driving force
            if ($signal->created_driving_force_id) {
                $createdDf = $drivingForces->firstWhere('id', $signal->created_driving_force_id);
                if ($createdDf) {
                    $linkedDfUuids[] = $createdDf->uuid;
                }
            }

            // Suggested driving force
            if ($signal->suggested_driving_force_id) {
                $suggestedDf = $drivingForces->firstWhere('id', $signal->suggested_driving_force_id);
                if ($suggestedDf) {
                    $linkedDfUuids[] = $suggestedDf->uuid;
                }
            }

            $linkedDfUuids = array_unique($linkedDfUuids);

            foreach ($linkedDfUuids as $dfUuid) {
                $dfNodeId = 'df-'.$dfUuid;
                if (isset($nodeIdSet[$dfNodeId])) {
                    $links[] = [
                        'source' => $signalNodeId,
                        'target' => $dfNodeId,
                        'type' => 'signal_to_df',
                        'label' => 'Mendukung',
                        'weight' => (float) ($signal->confidence_score ?? 0.8),
                    ];
                }
            }
        }

        // Calculate degrees for sizing & centrality
        $degreeCounts = [];
        foreach ($links as $link) {
            $degreeCounts[$link['source']] = ($degreeCounts[$link['source']] ?? 0) + 1;
            $degreeCounts[$link['target']] = ($degreeCounts[$link['target']] ?? 0) + 1;
        }

        foreach ($nodes as &$node) {
            $deg = $degreeCounts[$node['id']] ?? 0;
            $node['degree'] = $deg;
            if ($node['type'] === 'driving_force') {
                $node['val'] = max(18, min(36, 18 + $deg * 2));
            }
        }
        unset($node);

        $dimensions = Dimension::query()->pluck('name')->values()->all();

        return [
            'nodes' => $nodes,
            'links' => $links,
            'stats' => [
                'total_nodes' => count($nodes),
                'total_links' => count($links),
                'driving_forces_count' => $drivingForces->count(),
                'signals_count' => $signals->count(),
                'sources_count' => $sources->count(),
            ],
            'dimensions' => $dimensions,
        ];
    }
}
