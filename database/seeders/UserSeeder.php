<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $users, $roles, $permissions;

    /**
     * Realistic synthetic internal foresight and strategic planning team profiles.
     */
    protected const SYNTHETIC_TEAM_PROFILES = [
        ['name' => 'Budi Santoso', 'department' => 'Corporate Planning', 'role' => 'admin'],
        ['name' => 'Rini Indrawati', 'department' => 'Market Intelligence', 'role' => 'admin'],
        ['name' => 'Bambang Wijaya', 'department' => 'Government Relations', 'role' => 'admin'],
        ['name' => 'Siti Nurhaliza', 'department' => 'ESG & Sustainability', 'role' => 'admin'],
        ['name' => 'Dewi Lestari', 'department' => 'R&D Powertrain', 'role' => 'admin'],
        ['name' => 'Aditya Pratama', 'department' => 'Product Strategy', 'role' => 'admin'],
        ['name' => 'Hendro Gunawan', 'department' => 'Supply Chain Risk', 'role' => 'admin'],
        ['name' => 'Maya Anggraini', 'department' => 'Customer Insights', 'role' => 'admin'],
        ['name' => 'Fajar Hidayat', 'department' => 'Regulatory Affairs', 'role' => 'admin'],
        ['name' => 'Nadia Safitri', 'department' => 'Mobility Ecosystem', 'role' => 'admin'],
        ['name' => 'Arya Kusuma', 'department' => 'Executive Strategic Board', 'role' => 'bod'],
        ['name' => 'Tri Wahyuni', 'department' => 'Executive Risk Committee', 'role' => 'bod'],
    ];

    public function __construct()
    {
        $this->users = json_decode(file_get_contents(__DIR__ . '../../data/user.json'));
        $this->roles = json_decode(file_get_contents(__DIR__ . '../../data/role.json'));
        $this->permissions = json_decode(file_get_contents(__DIR__ . '../../data/permission.json'));
    }

    public function run(): void
    {
        // 1. Initial permissions
        $this->command->info('initial permissions');
        $this->command->getOutput()->progressStart(count($this->permissions));
        foreach ($this->permissions as $permission) {
            $this->initializePermission($permission);
            $this->command->getOutput()->progressAdvance();
        }

        // 2. Initial roles
        $this->command->info('initial roles');
        $this->command->getOutput()->progressStart(count($this->roles));
        foreach ($this->roles as $role) {
            $this->initializeRole($role);
            $this->command->getOutput()->progressAdvance();
        }

        // 3. Initial core system users (Developer, Super Admin, Test BOD)
        $this->command->info('initial user');
        $this->command->getOutput()->progressStart(count($this->users));
        foreach ($this->users as $user) {
            $this->initializeUser($user);
            $this->command->getOutput()->progressAdvance();
        }

        // 4. Initial synthetic foresight analyst & executive team (strictly non-Lorem, realistic corporate team)
        if (config('app.env') === 'local' || app()->environment('local', 'testing')) {
            $this->command->info('initial synthetic foresight analysts & executives');
            $this->command->getOutput()->progressStart(count(self::SYNTHETIC_TEAM_PROFILES));
            foreach (self::SYNTHETIC_TEAM_PROFILES as $profile) {
                $this->initializeSyntheticUser($profile);
                $this->command->getOutput()->progressAdvance();
            }
        }
    }

    protected function initializePermission($permission): void
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

    protected function initializeRole($role): void
    {
        $newRole = Role::firstOrCreate([
            'name' => $role->name,
        ], [
            'uuid' => Uuid::uuid1(),
            'display_name' => $role->display_name,
            'guard_name' => 'web',
        ]);

        $newRole->syncPermissions($role->permissions);
    }

    protected function initializeUser($user): void
    {
        $newUser = User::firstOrCreate([
            'name' => $user->name,
        ], [
            'uuid' => Uuid::uuid1(),
            'email' => $user->email,
            'email_verified_at' => now(),
            'password' => bcrypt($user->password),
            'remember_token' => null,
        ]);

        $newUser->syncRoles($user->role);
        $role = Role::where('name', $newUser->roles->pluck('name')[0])->first();
        if ($role) {
            $newUser->syncPermissions($role->permissions);
        }
    }

    protected function initializeSyntheticUser(array $profile): void
    {
        $slug = str()->slug($profile['name'], '.');
        $email = $slug . '@foresight.internal';

        $newUser = User::firstOrCreate([
            'email' => $email,
        ], [
            'uuid' => Uuid::uuid1(),
            'name' => $profile['name'],
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'remember_token' => null,
            'created_at' => now()->subDays(rand(10, 60)),
        ]);

        $role = Role::where('name', $profile['role'])->first();
        if ($role) {
            $newUser->syncRoles($role->name);
            $newUser->syncPermissions($role->permissions);
        }
    }
}
