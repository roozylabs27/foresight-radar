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
    }
}
