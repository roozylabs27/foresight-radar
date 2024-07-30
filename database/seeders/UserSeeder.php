<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $users;

    public function __construct()
    {
        $this->users = json_decode(file_get_contents(__DIR__ . '../../data/user.json', true));
    }

    public function run(): void
    {
        $this->command->info('initial user');
        $this->command->getOutput()->progressStart(count($this->users));
        foreach ($this->users as $user) {
            $this->initializeDefaultUser($user);
            $this->command->getOutput()->progressAdvance();
        }
    }

    protected function initializeDefaultUser($user): void
    {
        User::firstOrCreate(
            [
                'email' => $user->email
            ],
            [
                'name' => $user->name,
                'uuid' => Uuid::uuid1(),
                'email' => $user->email,
                'password' => Hash::make($user->password)
            ]
        );
    }
}
