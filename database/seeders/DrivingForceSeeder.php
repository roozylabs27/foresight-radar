<?php

namespace Database\Seeders;

use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class DrivingForceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $driving_forces, $driving_force_ratings;

    public function __construct()
    {
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
        $user = User::first();

        DrivingForce::firstOrCreate([
            'keyword' => $driving_force->keyword
        ],
        [
            'uuid' => Uuid::uuid1(),
            'dimension_id' => $driving_force->dimension_id,
            'description' => $driving_force->description,
            'status' => 'APPROVED',
            'created_by' => $user ? $user->id : 1,
        ]);
    }

    public function initializeDefaultDrivingForceRating($rating)
    {
        DrivingForceRating::firstOrCreate([
            'driving_force_id' => $rating->driving_force_id,
        ],
        [
            'uuid' => Uuid::uuid1(),
            'time_horizon_id' => $rating->time_horizon_id,
            'status_action_id' => $rating->status_action_id,
            'priority_id' => $rating->priority_id,
            'impact_analysis' => $rating->impact_analysis,
            'uncertainty_analysis' => $rating->uncertainty_analysis,
        ]);
    }
}
