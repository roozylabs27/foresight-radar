<?php

namespace Database\Seeders;

use App\Models\DrivingForce;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class DrivingForceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $driving_forces;

    public function __construct()
    {
        $this->driving_forces = json_decode(file_get_contents(__DIR__ . '../../data/driving_force.json', true));
    }

    public function run(): void
    {
        $this->command->info('initial driving force data');
        $this->command->getOutput()->progressStart(count($this->driving_forces));
        foreach ($this->driving_forces as $driving_force) {
            $this->initializeDefaultDrivingForce($driving_force);
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
            'created_by' => $user ? $user->id : 1,
        ]);
    }
}
