<?php

namespace App\Providers;

use App\Models\DrivingForce;
use App\Models\Scenario;
use App\Models\User;
use App\Policies\DrivingForcePolicy;
use App\Policies\ScenarioPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        DrivingForce::class => DrivingForcePolicy::class,
        User::class => UserPolicy::class,
        Scenario::class => ScenarioPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
