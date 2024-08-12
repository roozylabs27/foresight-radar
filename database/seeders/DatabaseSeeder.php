<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info(PHP_EOL);
        $this->command->info('🧑🏻‍💻 Dummy Default System ' . PHP_EOL);
        $this->command->info('************ Default Token System ************' . PHP_EOL);
        $this->command->info('************************************' . PHP_EOL);

        $this->call([
            DefaultSystemSeeder::class,
        ]);

        $this->command->info(PHP_EOL);
        $this->command->info('🧑🏻‍💻 Dummy User Data' . PHP_EOL);
        $this->command->info('************************************' . PHP_EOL);

        $this->call([
            UserSeeder::class,
        ]);

        $this->command->info(PHP_EOL);
        $this->command->info('🧑🏻‍💻 Dummy Driving Force Data ' . PHP_EOL);
        $this->command->info('************************************' . PHP_EOL);

        $this->call([
            DrivingForceSeeder::class,
        ]);
    }
}
