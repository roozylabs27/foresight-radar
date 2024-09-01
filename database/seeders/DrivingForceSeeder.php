<?php

namespace Database\Seeders;

use App\Models\ActionReason;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\StatusAction;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;
use Faker\Factory as Dummy;

class DrivingForceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $driving_forces, $driving_force_ratings, $dummy;

    public function __construct()
    {
        $this->dummy = Dummy::create('id_ID');
        $this->driving_forces = json_decode(file_get_contents(__DIR__ . '../../data/driving_force.json', true));
        $this->driving_force_ratings = json_decode(file_get_contents(__DIR__ . '../../data/driving_force_rating.json', true));
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

    public function initializeDefaultDrivingForce($driving_force)
    {
        DrivingForce::firstOrCreate([
            'keyword' => $driving_force->keyword
        ],
        [
            'uuid' => Uuid::uuid1(),
            'dimension_id' => $driving_force->dimension_id,
            'description' => $driving_force->description,
            'status' => 'APPROVED',
            'created_by' => User::all()->random()->id,
            'updated_by' => User::all()->random()->id,
            'pic' => User::all()->random()->id,
            'approved_at' => now()->subDays(rand(0, 30))
        ]);
    }

    public function initializeDefaultDrivingForceRating($rating)
    {
        $rating = DrivingForceRating::firstOrCreate([
            'driving_force_id' => $rating->driving_force_id,
            'status_action_id' => $rating->status_action_id,
        ],
        [
            'uuid' => Uuid::uuid1(),
            'time_horizon_id' => $rating->time_horizon_id,
            'priority_id' => $rating->priority_id,
            'impact_analysis' => $rating->impact_analysis,
            'uncertainty_analysis' => $rating->uncertainty_analysis,
        ]);

        for($i=mt_rand(1,3); $i >= 1; $i--) {
            ActionReason::create([
                'uuid' => Uuid::uuid1(),
                'driving_force_rating_id' => $rating->id,
                'status_action_id' => StatusAction::all()->random()->id,
                'date' => now()->subDays($i)->subHours(rand(0,24))->subMinutes(rand(0,60))->subSeconds(rand(0,60)),
                'reason' => $this->dummy->paragraph(rand(1,3))
            ]);
        }

        $action_reason = ActionReason::where('driving_force_rating_id', $rating->id)->latest('id')->first();
        $rating->status_action_id = $action_reason->status_action_id;
        $rating->save();
    }
}
