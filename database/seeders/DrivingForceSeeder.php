<?php

namespace Database\Seeders;

use App\Models\ActionReason;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\StatusAction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class DrivingForceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $driving_forces;

    protected $driving_force_ratings;

    protected array $synthetic_reasons = [];

    public function __construct()
    {
        $this->driving_forces = json_decode(file_get_contents(database_path('data/driving_force.json')));
        $this->driving_force_ratings = json_decode(file_get_contents(database_path('data/driving_force_rating.json')));
        $reasonsFile = database_path('data/action_reason.json');
        if (file_exists($reasonsFile)) {
            $this->synthetic_reasons = json_decode(file_get_contents($reasonsFile), true) ?: [];
        }
    }

    public function run(): void
    {
        $this->command->info('initial driving force data');
        $this->command->getOutput()->progressStart(count($this->driving_forces));
        foreach ($this->driving_forces as $driving_force) {
            $this->initializeDefaultDrivingForce($driving_force);
            $this->command->getOutput()->progressAdvance();
        }

        $this->command->info('initial driving force rating data');
        $this->command->getOutput()->progressStart(count($this->driving_force_ratings));
        foreach ($this->driving_force_ratings as $rating) {
            $this->initializeDefaultDrivingForceRating($rating);
            $this->command->getOutput()->progressAdvance();
        }
    }

    public function initializeDefaultDrivingForce($driving_force): void
    {
        DrivingForce::firstOrCreate([
            'keyword' => $driving_force->keyword,
        ], [
            'uuid' => Uuid::uuid1(),
            'dimension_id' => $driving_force->dimension_id,
            'description' => $driving_force->description,
            'status' => 'APPROVED',
            'created_by' => User::all()->random()->id,
            'updated_by' => User::all()->random()->id,
            'pic' => User::all()->random()->id,
            'approved_at' => now()->subDays(rand(10, 45)),
        ]);
    }

    public function initializeDefaultDrivingForceRating($rating): void
    {
        $createdRating = DrivingForceRating::firstOrCreate([
            'driving_force_id' => $rating->driving_force_id,
            'status_action_id' => $rating->status_action_id,
        ], [
            'uuid' => Uuid::uuid1(),
            'time_horizon_id' => $rating->time_horizon_id,
            'priority_id' => $rating->priority_id,
            'impact_analysis' => $rating->impact_analysis,
            'uncertainty_analysis' => $rating->uncertainty_analysis,
        ]);

        $df = DrivingForce::find($rating->driving_force_id);
        $statusActions = StatusAction::all()->keyBy('name');
        $monitoringStatus = $statusActions->get('Monitoring') ?? StatusAction::first();
        $decidedStatus = $statusActions->get('Decided Plan') ?? StatusAction::orderBy('id', 'desc')->first();

        $isFinalDecided = ((int) $rating->status_action_id === (int) $decidedStatus->id);

        // Reset previous seeded reasons if re-running seeder so no stale Lorem Ipsum remains
        ActionReason::where('driving_force_rating_id', $createdRating->id)->delete();

        // 1) Historical surveillance milestone (25-45 days ago): initial Monitoring
        $firstDate = now()->subDays(rand(25, 45))->subHours(rand(1, 12))->subMinutes(rand(1, 50));
        ActionReason::create([
            'uuid' => Uuid::uuid1(),
            'driving_force_rating_id' => $createdRating->id,
            'status_action_id' => $monitoringStatus->id,
            'date' => $firstDate,
            'reason' => $this->getSyntheticReason($df?->keyword, 'Monitoring', 1),
        ]);

        // 2) Current assessment milestone (2-8 days ago): Monitoring or Decided Plan based on target rating
        $secondStatus = $isFinalDecided ? $decidedStatus : $monitoringStatus;
        $secondDate = now()->subDays(rand(2, 8))->subHours(rand(1, 12))->subMinutes(rand(1, 50));
        ActionReason::create([
            'uuid' => Uuid::uuid1(),
            'driving_force_rating_id' => $createdRating->id,
            'status_action_id' => $secondStatus->id,
            'date' => $secondDate,
            'reason' => $this->getSyntheticReason($df?->keyword, $secondStatus->name, 2),
        ]);

        $createdRating->status_action_id = $secondStatus->id;
        $createdRating->save();
    }

    /**
     * Resolves an authentic, non-Lorem synthetic rationale based on keyword, status, and chronological step.
     */
    public function getSyntheticReason(?string $keyword, string $statusActionName, int $step = 1): string
    {
        $statusKey = str_contains(strtolower($statusActionName), 'decided') ? 'Decided Plan' : 'Monitoring';

        if ($keyword && isset($this->synthetic_reasons[$keyword][$statusKey])) {
            $pool = $this->synthetic_reasons[$keyword][$statusKey];
            $index = ($step - 1) % count($pool);

            return $pool[$index];
        }

        // Professional synthetic fallback for custom driving forces (strictly no Lorem Ipsum).
        $label = $keyword ?: 'faktor pendorong strategis';
        if ($statusKey === 'Decided Plan') {
            return "Komite manajemen menyetujui rencana aksi mitigasi dan penyesuaian alokasi sumber daya operasional terkait tren terkini pada {$label}.";
        }

        return "Pemantauan berkala dan analisis risiko berkelanjutan terhadap perkembangan indikator {$label} serta implikasinya pada daya saing perusahaan.";
    }
}
