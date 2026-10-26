<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $users;

    protected $roles;

    protected $permissions;

    protected $synthetic_users;

    protected ?Tenant $defaultTenant = null;

    public function __construct()
    {
        $this->users = json_decode(file_get_contents(database_path('data/user.json')));
        $this->roles = json_decode(file_get_contents(database_path('data/role.json')));
        $this->permissions = json_decode(file_get_contents(database_path('data/permission.json')));
        $syntheticFile = database_path('data/synthetic_user.json');
        $this->synthetic_users = file_exists($syntheticFile)
            ? json_decode(file_get_contents($syntheticFile), true)
            : [];
    }

    public function run(): void
    {
        $this->defaultTenant = Tenant::firstOrCreate([
            'slug' => 'default-org',
        ], [
            'uuid' => (string) Uuid::uuid4(),
            'name' => config('app.name', 'Foresight Radar').' Workspace',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_users' => 100,
        ]);

        app(TenantContext::class)->setTenant($this->defaultTenant);

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

        // 4. Initial synthetic foresight analyst & executive team (loaded from synthetic_user.json)
        if ((config('app.env') === 'local' || app()->environment('local', 'testing')) && ! empty($this->synthetic_users)) {
            $this->command->info('initial synthetic foresight analysts & executives');
            $this->command->getOutput()->progressStart(count($this->synthetic_users));
            foreach ($this->synthetic_users as $profile) {
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
            'tenant_id' => $this->defaultTenant?->id,
            'email' => $user->email,
            'email_verified_at' => now(),
            'password' => bcrypt($user->password),
            'remember_token' => null,
        ]);

        if ($this->defaultTenant && ! $newUser->tenant_id) {
            $newUser->forceFill(['tenant_id' => $this->defaultTenant->id])->save();
        }

        $newUser->syncRoles($user->role);
        $role = Role::where('name', $newUser->roles->pluck('name')[0])->first();
        if ($role) {
            $newUser->syncPermissions($role->permissions);
        }
    }

    protected function initializeSyntheticUser(array $profile): void
    {
        $email = $profile['email'] ?? (str()->slug($profile['name'], '.').'@foresight.internal');

        $newUser = User::firstOrCreate([
            'email' => $email,
        ], [
            'uuid' => Uuid::uuid1(),
            'tenant_id' => $this->defaultTenant?->id,
            'name' => $profile['name'],
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'remember_token' => null,
            'created_at' => now()->subDays(rand(10, 60)),
        ]);

        if ($this->defaultTenant && ! $newUser->tenant_id) {
            $newUser->forceFill(['tenant_id' => $this->defaultTenant->id])->save();
        }

        $role = Role::where('name', $profile['role'])->first();
        if ($role) {
            $newUser->syncRoles($role->name);
            $newUser->syncPermissions($role->permissions);
        }
    }
}
