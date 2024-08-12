<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Faker\Factory as Dummy;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $users, $roles, $permissions, $dummy;

    public function __construct()
    {
        $this->users = json_decode(file_get_contents(__DIR__ . '../../data/user.json', true));
        $this->roles = json_decode(file_get_contents(__DIR__ . '../../data/role.json', true));
        $this->permissions = json_decode(file_get_contents(__DIR__ . '../../data/permission.json', true));
        $this->dummy = Dummy::create('id_ID');
    }

    public function run(): void
    {
        // initial permission
        $this->command->info('initial permissions');
        $this->command->getOutput()->progressStart(count($this->permissions));
        foreach ($this->permissions as $permission) {
            $this->initializePermission($permission);
            $this->command->getOutput()->progressAdvance();
        }

        // initial role
        $this->command->info('initial roles');
        $this->command->getOutput()->progressStart(count($this->roles));
        foreach ($this->roles as $role) {
            $this->initializeRole($role);
            $this->command->getOutput()->progressAdvance();
        }

        // initial user default
        $this->command->info('initial user');
        $this->command->getOutput()->progressStart(count($this->users));
        foreach ($this->users as $user) {
            $this->initializeUser($user);
            $this->command->getOutput()->progressAdvance();
        }

        // initial user default
        $this->command->info('initial user');
        $this->command->getOutput()->progressStart(count($this->users));
        foreach ($this->users as $user) {
            $this->initializeUser($user);
            $this->command->getOutput()->progressAdvance();
        }

        if (config('app.env') == 'local') {
            // initial user dummy admin
            $this->command->info('initial user dummy admin');
            $this->command->getOutput()->progressStart(100);
            for ($i = 0; $i < 100; $i++) {
                $this->initializeUserDummy($user);
                $this->command->getOutput()->progressAdvance();
            }
        }
    }


    protected function initializePermission($permission)
    {
        Permission::firstOrCreate([
            'name' => $permission->name,
        ], [
            'uuid' => Uuid::uuid1(),
            'description' => $permission->description,
            'category' => $permission->category,
            'guard_name' => 'web',
        ]);
    }

    protected function initializeRole($role)
    {
        $new_role = Role::firstOrCreate([
            'name' => $role->name,
        ], [
            'uuid' => Uuid::uuid1(),
            'display_name' => $role->display_name,
            'guard_name' => 'web'
        ]);

        $new_role->givePermissionTo($role->permissions);
    }

    protected function initializeUser($user)
    {
        $new_user = User::firstOrCreate([
            "name" => $user->name,
        ], [
            "uuid" => Uuid::uuid1(),
            "email" => $user->email,
            "email_verified_at" => null,
            "password" => bcrypt($user->password),
            "remember_token" => null,
        ]);


        $new_user->syncRoles($user->role);

        $role = Role::where('name', $new_user->roles->pluck('name')[0])->first();

        $new_user->syncPermissions($role->permissions);
    }

    protected function initializeUserDummy()
    {
        $name = $this->dummy->firstName() . ' ' . $this->dummy->lastName();

        $new_user = User::firstOrCreate([
            "name" => $name,
        ], [
            "uuid" => Uuid::uuid1(),
            "email" => $this->generateEmailFromName($name),
            "email_verified_at" => null,
            "password" => bcrypt('password'),
            "remember_token" => null,
            "created_at" => now()->subDays(rand(1, 7))
        ]);

        $get_random_role = Role::whereNotIn('name', ['developer', 'qa', 'super-admin'])->first();

        $new_user->syncRoles($get_random_role->name);

        $new_user->syncPermissions($get_random_role->permissions);
    }

    private function generateEmailFromName($name)
    {
        $name = str()->slug($name, '.'); // Converts the name to a slug format, e.g., John Doe -> john.doe
        return $name . $this->dummy->randomNumber(rand(1, 2)) . '@example.com'; // You can replace 'example.com' with any domain you prefer
    }
}
