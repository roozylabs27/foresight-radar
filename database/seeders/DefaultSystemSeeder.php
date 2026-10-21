<?php

namespace Database\Seeders;

use App\Models\Dimension;
use App\Models\Environment;
use App\Models\Priority;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class DefaultSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $environments;

    protected $dimensions;

    protected $time_horizons;

    protected $priorities;

    protected $status_actions;

    public function __construct()
    {
        $this->environments = json_decode(file_get_contents(database_path('data/environment.json')));
        $this->dimensions = json_decode(file_get_contents(database_path('data/dimension.json')));
        $this->time_horizons = json_decode(file_get_contents(database_path('data/time_horizon.json')));
        $this->priorities = json_decode(file_get_contents(database_path('data/priority.json')));
        $this->status_actions = json_decode(file_get_contents(database_path('data/status_action.json')));
    }

    public function run(): void
    {
        $this->command->info('initial environment');
        $this->command->getOutput()->progressStart(count($this->environments));
        foreach ($this->environments as $env) {
            $this->initializeDefaultEnv($env);
            $this->command->getOutput()->progressAdvance();
        }

        $this->command->info('initial dimension');
        $this->command->getOutput()->progressStart(count($this->dimensions));
        foreach ($this->dimensions as $dimension) {
            $this->initializeDefaultDimension($dimension);
            $this->command->getOutput()->progressAdvance();
        }

        $this->command->info('initial time horizon');
        $this->command->getOutput()->progressStart(count($this->time_horizons));
        foreach ($this->time_horizons as $time_horizon) {
            $this->initializeDefaultTimeHorizon($time_horizon);
            $this->command->getOutput()->progressAdvance();
        }

        $this->command->info('initial priority');
        $this->command->getOutput()->progressStart(count($this->priorities));
        foreach ($this->priorities as $priority) {
            $this->initializeDefaultPriority($priority);
            $this->command->getOutput()->progressAdvance();
        }

        $this->command->info('initial status action');
        $this->command->getOutput()->progressStart(count($this->status_actions));
        foreach ($this->status_actions as $status_action) {
            $this->initializeDefaultStatusAction($status_action);
            $this->command->getOutput()->progressAdvance();
        }
    }

    protected function initializeDefaultEnv($env): void
    {
        Environment::firstOrCreate([
            'name' => $env->name,
        ], [
            'uuid' => Uuid::uuid1(),
        ]);
    }

    protected function initializeDefaultDimension($dimension): void
    {
        Dimension::firstOrCreate([
            'name' => $dimension->name,
        ], [
            'uuid' => Uuid::uuid1(),
            'environment_id' => $dimension->environment_id,
        ]);
    }

    protected function initializeDefaultTimeHorizon($time_horizon): void
    {
        TimeHorizon::firstOrCreate([
            'name' => $time_horizon->name,
        ], [
            'uuid' => Uuid::uuid1(),
            'code' => $time_horizon->code,
        ]);
    }

    protected function initializeDefaultPriority($priority): void
    {
        Priority::firstOrCreate([
            'name' => $priority->name,
        ], [
            'uuid' => Uuid::uuid1(),
            'color' => $priority->color,
        ]);
    }

    protected function initializeDefaultStatusAction($status_action): void
    {
        StatusAction::firstOrCreate([
            'name' => $status_action->name,
        ], [
            'uuid' => Uuid::uuid1(),
            'symbol' => $status_action->symbol,
            'code' => $status_action->code,
        ]);
    }
}
