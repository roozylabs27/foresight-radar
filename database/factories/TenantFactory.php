<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'domain' => fake()->unique()->safeEmailDomain(),
            'status' => 'active',
            'plan' => 'professional',
            'max_users' => 25,
            'storage_quota_mb' => 10240,
            'llm_monthly_token_quota' => 10000000,
            'settings' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => 'suspended',
        ]);
    }
}
