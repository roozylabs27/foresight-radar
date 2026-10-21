<?php

namespace App\Services;

use App\Models\CrossImpact;
use App\Models\DrivingForce;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Cross-impact matrix between approved driving forces (INT-013, smallest
 * useful version) and the Vester influence summary per force:
 * - active  = Σ|score| of what the force does to others (its row)
 * - passive = Σ|score| of what others do to it (its column)
 * Compared with the matrix averages, a force is a driver (active only),
 * critical (both), indicator (passive only) or buffer (neither).
 */
class CrossImpactService
{
    public const ROLES = [
        'driver' => 'Pendorong',
        'critical' => 'Kritis',
        'indicator' => 'Indikator',
        'buffer' => 'Penyangga',
        'unscored' => 'Belum dinilai',
    ];

    public function __construct(protected CausalLoopService $loops)
    {
    }

    public function matrix(): array
    {
        $forces = DrivingForce::query()
            ->where('status', 'APPROVED')
            ->with('dimension')
            ->orderBy('dimension_id')
            ->orderBy('keyword')
            ->get();

        $ids = $forces->pluck('id');
        $impacts = CrossImpact::query()
            ->whereIn('source_driving_force_id', $ids)
            ->whereIn('target_driving_force_id', $ids)
            ->get();

        $uuid = $forces->pluck('uuid', 'id');
        $summary = $this->summary($forces, $impacts);
        $loops = $this->loops->detect(
            $forces->pluck('uuid')->all(),
            $impacts->map(fn ($impact) => [
                'source' => $uuid[$impact->source_driving_force_id],
                'target' => $uuid[$impact->target_driving_force_id],
                'score' => $impact->score,
            ])->all(),
        );

        return [
            'forces' => $forces->map(fn ($force) => [
                'id' => $force->uuid,
                'keyword' => $force->keyword,
                'dimension' => $force->dimension?->name,
            ])->values()->all(),
            'cells' => $impacts->map(fn ($impact) => [
                'source' => $uuid[$impact->source_driving_force_id],
                'target' => $uuid[$impact->target_driving_force_id],
                'score' => $impact->score,
                'note' => $impact->note,
            ])->values()->all(),
            'summary' => $summary['rows'],
            'thresholds' => $summary['thresholds'],
            'scored_pairs' => $impacts->where('score', '!=', 0)->count(),
            'possible_pairs' => $forces->count() * max(0, $forces->count() - 1),
            'loops' => $loops,
        ];
    }

    /**
     * Set how `source` affects `target`. A score of 0 without a note removes
     * the pair, since a missing pair already means no influence.
     */
    public function score(string $sourceUuid, string $targetUuid, int $score, ?string $note, ?int $userId): ?CrossImpact
    {
        $forces = DrivingForce::whereIn('uuid', [$sourceUuid, $targetUuid])->get()->keyBy('uuid');
        foreach (['source' => $sourceUuid, 'target' => $targetUuid] as $field => $uuid) {
            if (($forces[$uuid]->status ?? null) !== 'APPROVED') {
                throw ValidationException::withMessages([$field => 'Cross-impact hanya untuk driving force yang sudah APPROVED.']);
            }
        }

        $keys = [
            'source_driving_force_id' => $forces[$sourceUuid]->id,
            'target_driving_force_id' => $forces[$targetUuid]->id,
        ];
        $note = trim((string) $note) === '' ? null : trim($note);

        if ($score === 0 && $note === null) {
            CrossImpact::where($keys)->delete();
            return null;
        }

        $impact = CrossImpact::firstOrNew($keys);
        $impact->fill(['score' => $score, 'note' => $note, 'updated_by' => $userId]);
        $impact->created_by ??= $userId;
        $impact->save();

        return $impact;
    }

    /**
     * @param  Collection<int, DrivingForce>  $forces
     * @param  Collection<int, CrossImpact>  $impacts
     */
    public function summary(Collection $forces, Collection $impacts): array
    {
        $rows = $forces->map(function ($force) use ($impacts) {
            $outgoing = $impacts->where('source_driving_force_id', $force->id);
            $incoming = $impacts->where('target_driving_force_id', $force->id);

            return [
                'id' => $force->uuid,
                'keyword' => $force->keyword,
                'dimension' => $force->dimension?->name,
                'active' => (int) $outgoing->sum(fn ($impact) => abs($impact->score)),
                'passive' => (int) $incoming->sum(fn ($impact) => abs($impact->score)),
                'net' => (int) $outgoing->sum('score'),
                'links' => $outgoing->where('score', '!=', 0)->count() + $incoming->where('score', '!=', 0)->count(),
            ];
        })->values();

        $count = max(1, $rows->count());
        $thresholds = [
            'active' => round($rows->sum('active') / $count, 2),
            'passive' => round($rows->sum('passive') / $count, 2),
        ];

        $rows = $rows->map(function ($row) use ($thresholds) {
            $active = $row['active'] > $thresholds['active'];
            $passive = $row['passive'] > $thresholds['passive'];
            $role = match (true) {
                $row['active'] === 0 && $row['passive'] === 0 => 'unscored',
                $active && $passive => 'critical',
                $active => 'driver',
                $passive => 'indicator',
                default => 'buffer',
            };

            return $row + ['role' => $role, 'role_label' => self::ROLES[$role]];
        });

        return [
            'rows' => $rows
                ->sort(fn ($a, $b) => ($b['active'] + $b['passive']) <=> ($a['active'] + $a['passive'])
                    ?: $b['active'] <=> $a['active']
                    ?: strcmp((string) $a['keyword'], (string) $b['keyword']))
                ->values()
                ->all(),
            'thresholds' => $thresholds,
        ];
    }
}
