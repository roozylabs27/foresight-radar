<?php

namespace Database\Seeders;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Priority;
use App\Models\ScenarioPlanningProject;
use App\Models\StatusAction;
use App\Models\Tenant;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\TenantContext;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * Fixtures of the isolated browser E2E stack (docker-compose.e2e.yml; tests/browser). Two
 * organisations, each with its own users, so the tests can prove tenant isolation; organisation A
 * has a rated, approved driving force library for the exercise journey and the FIRM reports.
 *
 *   php artisan migrate:fresh --seed --seeder=E2eSeeder --force     (inside e2e_app, never elsewhere)
 *
 * Refuses to run outside APP_ENV=e2e. The password is the throw-away E2E_PASSWORD of
 * docker/e2e/e2e.env (local only, never a real account).
 */
class E2eSeeder extends UserSeeder
{
    public const ADMIN_A = 'admin-a@e2e.test';

    public const VIEWER_A = 'viewer-a@e2e.test';

    public const ADMIN_B = 'admin-b@e2e.test';

    public function run(): void
    {
        if (! app()->environment('e2e')) {
            throw new RuntimeException('E2eSeeder only runs in the e2e environment.');
        }
        $password = (string) env('E2E_PASSWORD');
        if (strlen($password) < 12) {
            throw new RuntimeException('Set E2E_PASSWORD (12+ characters) in docker/e2e/e2e.env.');
        }

        $this->call(DefaultSystemSeeder::class);
        foreach ($this->permissions as $permission) {
            $this->initializePermission($permission);
        }
        foreach ($this->roles as $role) {
            $this->initializeRole($role);
        }

        $a = $this->tenant('Organisasi Uji A', 'e2e-a');
        $adminA = $this->user($a, 'Admin Uji A', self::ADMIN_A, 'admin', $password);
        $this->user($a, 'Direksi Uji A', self::VIEWER_A, 'bod', $password);
        $this->library($a, $adminA);

        $b = $this->tenant('Organisasi Uji B', 'e2e-b');
        $adminB = $this->user($b, 'Admin Uji B', self::ADMIN_B, 'admin', $password);
        app(TenantContext::class)->setTenant($b);
        // Organisation B's private work: organisation A must never see it.
        ScenarioPlanningProject::create([
            'uuid' => (string) Uuid::uuid4(), 'name' => 'Rahasia Organisasi B', 'focal_question' => 'Bagaimana pasar baterai berubah hingga 2035?',
            'purpose' => 'Rahasia B.', 'target_year' => now()->year + 9, 'status' => 'draft', 'created_by' => $adminB->id,
        ]);
        DrivingForce::create([
            'uuid' => (string) Uuid::uuid4(), 'dimension_id' => Dimension::where('code', 'TECHNOLOGY')->value('id'), 'created_by' => $adminB->id,
            'keyword' => 'Rahasia B Force', 'description' => 'Hanya organisasi B.', 'status' => 'APPROVED',
        ]);
        app(TenantContext::class)->setTenant(null);
    }

    private function tenant(string $name, string $slug): Tenant
    {
        return Tenant::create(['uuid' => (string) Uuid::uuid4(), 'name' => $name, 'slug' => $slug, 'status' => 'active', 'plan' => 'enterprise', 'max_users' => 50]);
    }

    private function user(Tenant $tenant, string $name, string $email, string $role, string $password): User
    {
        $user = User::create([
            'uuid' => (string) Uuid::uuid4(), 'tenant_id' => $tenant->id, 'name' => $name, 'email' => $email,
            'email_verified_at' => now(), 'password' => bcrypt($password),
        ]);
        $user->syncRoles($role);

        return $user;
    }

    /** Four approved, fully rated forces in different method categories (two can become axes). */
    private function library(Tenant $tenant, User $owner): void
    {
        app(TenantContext::class)->setTenant($tenant);
        $horizon = TimeHorizon::firstOrCreate(['code' => 'MT'], ['uuid' => (string) Uuid::uuid4(), 'name' => 'Mid Term']);
        $action = StatusAction::query()->first();
        $priority = Priority::query()->first();
        $forces = [
            'Regulasi TKDN' => ['LEGISLATIVE', 9, 8],
            'Teknologi Baterai Padat' => ['TECHNOLOGY', 8, 9],
            'Daya Beli Konsumen' => ['CONSUMER', 7, 4],
            'Stabilitas Politik' => ['POLITICAL', 6, 5],
        ];
        foreach ($forces as $keyword => [$code, $impact, $uncertainty]) {
            $force = DrivingForce::create([
                'uuid' => (string) Uuid::uuid4(), 'dimension_id' => Dimension::where('code', $code)->value('id'), 'created_by' => $owner->id,
                'pic' => $owner->id, 'keyword' => $keyword, 'description' => "{$keyword} (data uji E2E).", 'status' => 'APPROVED', 'approved_at' => now(),
            ]);
            DrivingForceRating::create([
                'driving_force_id' => $force->id, 'time_horizon_id' => $horizon->id, 'status_action_id' => $action?->id, 'priority_id' => $priority?->id,
                'impact_analysis' => $impact, 'uncertainty_analysis' => $uncertainty,
            ]);
        }
        app(TenantContext::class)->setTenant(null);
    }
}
