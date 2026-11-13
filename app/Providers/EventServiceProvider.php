<?php

namespace App\Providers;

use App\Models\CriticalUncertainty;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Scenario;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\StrategicResponse;
use App\Observers\DrivingForceObserver;
use App\Observers\DrivingForceRatingObserver;
use App\Observers\GateDecisionObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * The model observers for your application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $observers = [
        DrivingForce::class => [DrivingForceObserver::class],
        DrivingForceRating::class => [DrivingForceRatingObserver::class],
        ScenarioPlanningProject::class => [GateDecisionObserver::class],
        CriticalUncertainty::class => [GateDecisionObserver::class],
        Scenario::class => [GateDecisionObserver::class],
        ScenarioQuadrant::class => [GateDecisionObserver::class],
        StrategicResponse::class => [GateDecisionObserver::class],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
