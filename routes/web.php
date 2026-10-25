<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ClosedItemsController;
use App\Http\Controllers\CrossImpactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DrivingForceController;
use App\Http\Controllers\ForesightRadarController;
use App\Http\Controllers\KnowledgeGraphController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PrioritizingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingUrgencyController;
use App\Http\Controllers\RegisteredListController;
use App\Http\Controllers\ScenarioController;
use App\Http\Controllers\Settings\OrganizationController;
use App\Http\Controllers\Settings\TenantMemberController;
use App\Http\Controllers\Settings\UserLlmSettingController;
use App\Http\Controllers\SignalIngestionController;
use App\Http\Controllers\StatusActionController;
use App\Http\Controllers\TimeHorizonController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return to_route('login');
});

Route::post('/locale', [LocaleController::class, 'update'])
    ->name('locale.update')
    ->middleware('throttle:60,1');

Route::prefix('/')->middleware(['auth', 'verified', 'throttle:120,1'])->group(function () {
    Route::prefix('/dashboard')->controller(DashboardController::class)->name('dashboard.')->group(function () {
        Route::get('/', 'index')->can('view-dashboard');
        Route::get('/reasoning-data', 'reasoning_data')->name('reasoning-data')->can('view-dashboard');
        Route::post('/simulate', 'simulate')->name('simulate')->can('view-dashboard')->middleware('throttle:60,1');
    });

    Route::prefix('visualization')->middleware('throttle:60,1')->name('visualization.')->group(function () {
        Route::prefix('/prioritizing')->name('prioritizing.')->group(function () {
            Route::get('/', [PrioritizingController::class, 'index'])->can('view-prioritizing');
            Route::get('/get-data', [PrioritizingController::class, 'prioritizing'])->name('get-data')->can('view-prioritizing');
        });
        Route::prefix('/registered-list')->name('registered-list.')->group(function () {
            Route::get('/', [RegisteredListController::class, 'index'])->can('view-registered-list');
            Route::get('/get-data', [RegisteredListController::class, 'registered_list'])->name('get-data')->can('view-registered-list');
            Route::get('/export-data', [RegisteredListController::class, 'export_data'])
                ->withoutMiddleware([AddLinkHeadersForPreloadedAssets::class])
                ->name('export-data')
                ->can('export-registered-list');
        });
        Route::prefix('/foresight-radar')->name('foresight-radar.')->group(function () {
            Route::get('/', [ForesightRadarController::class, 'index'])->can('view-foresight-radar');
            Route::get('/get-data', [ForesightRadarController::class, 'foresight_radar'])->name('get-data')->can('view-foresight-radar');
        });
        Route::prefix('/cross-impact')->name('cross-impact.')->group(function () {
            Route::get('/', [CrossImpactController::class, 'index'])->can('view-cross-impact');
            Route::get('/get-data', [CrossImpactController::class, 'data'])->name('get-data')->can('view-cross-impact');
        });
        Route::prefix('/knowledge-graph')->name('knowledge-graph.')->group(function () {
            Route::get('/', [KnowledgeGraphController::class, 'index'])->can('view-foresight-radar');
            Route::get('/index', [KnowledgeGraphController::class, 'index'])->name('index')->can('view-foresight-radar');
            Route::get('/data', [KnowledgeGraphController::class, 'data'])->name('data')->can('view-foresight-radar');
            Route::get('/get-data', [KnowledgeGraphController::class, 'data'])->name('get-data')->can('view-foresight-radar');
        });
    });

    // Scoring a matrix sends one request per cell, so it stays outside the visualization throttle.
    Route::post('/visualization/cross-impact', [CrossImpactController::class, 'update'])
        ->name('visualization.cross-impact.update')
        ->can('manage-cross-impact')
        ->middleware('throttle:240,1');

    Route::prefix('scenarios')->controller(ScenarioController::class)->name('scenarios.')->group(function () {
        Route::get('/', 'index')->name('index')->can('view-scenario');
        Route::get('/fetch', 'fetch')->name('fetch')->can('view-scenario');
        Route::get('/suggest-axes', 'suggestAxes')->name('suggest-axes')->can('view-scenario');
        Route::get('/{scenario}', 'show')->name('show')->can('view-scenario');
        Route::post('/', 'store')->name('store')->can('manage-scenario');
        Route::put('/{scenario}', 'update')->name('update')->can('manage-scenario');
        Route::delete('/{scenario}', 'destroy')->name('destroy')->can('manage-scenario');
        Route::put('/{scenario}/quadrants/{quadrant}', 'updateQuadrant')->name('quadrant')->can('manage-scenario');
        // One generation is one LLM call for all four quadrants.
        Route::post('/{scenario}/generate', 'generate')->name('generate')->can('manage-scenario')->middleware('throttle:10,1');
    });

    Route::prefix('signals')->controller(SignalIngestionController::class)->name('signals.')->group(function () {
        Route::get('/', 'index')->name('index')->can('view-signal');
        Route::get('/fetch', 'fetch_signals')->name('fetch')->can('view-signal');
        Route::post('/ingest', 'ingest')->name('ingest')->can('create-signal')->middleware('throttle:15,1');
        Route::get('/extractions/active', 'activeExtractions')->name('active-extractions')->can('view-signal');
        Route::get('/sources/{source}/extraction', 'extraction')->name('extraction')->can('view-signal');
        Route::post('/{signal}/review', 'review')->name('review')->can('review-signal');
    });

    Route::prefix('driving-force')->controller(DrivingForceController::class)->name('driving-force.')->group(function () {
        Route::get('/', 'index')->can('view-driving-force');
        Route::get('/fetch-data', 'fetch_data')->name('fetch-data')->can('view-driving-force');
        Route::post('/', 'create')->name('create')->can('create-driving-force');
        Route::put('/{driving_force}', 'update')->name('update')->can('edit-driving-force');
        Route::delete('/{driving_force}', 'delete')->name('delete')->can('delete-driving-force');
    });

    Route::prefix('time-horizon')->controller(TimeHorizonController::class)->name('time-horizon.')->group(function () {
        Route::get('/', 'index')->can('view-time-horizon');
        Route::get('/fetch-data', 'fetch_data')->name('fetch-data')->can('view-time-horizon');
        Route::post('/{driving_force:id}', 'create')->name('create')->can('create-time-horizon');
    });

    Route::prefix('rating-urgency')->controller(RatingUrgencyController::class)->name('rating-urgency.')->group(function () {
        Route::get('/', 'index')->can('view-rating-urgency');
        Route::get('/fetch-data', 'fetch_data')->name('fetch-data')->can('view-rating-urgency');
        Route::post('/{driving_force_rating}', 'create')->name('create')->can('create-rating-urgency');
    });

    Route::prefix('status-action')->controller(StatusActionController::class)->name('status-action.')->group(function () {
        Route::get('/', 'index')->can('view-status-action');
        Route::get('/fetch-data', 'fetch_data')->name('fetch-data')->can('view-status-action');
        Route::post('/{driving_force_rating}', 'create')->name('create')->can('create-status-action');
    });

    Route::prefix('approval-items')->controller(ApprovalController::class)->name('approval-items.')->group(function () {
        Route::get('/', 'index')->can('view-approval-items');
        Route::get('/fetch-data', 'fetch_data')->name('fetch-data')->can('view-approval-items');
        Route::post('/{driving_force}', 'create')->can('create-approval-items')->name('create');
    });

    Route::prefix('closed-items')->controller(ClosedItemsController::class)->name('closed-items.')->group(function () {
        Route::get('/', 'index')->can('view-closed-items');
        Route::get('/fetch-data', 'fetch_data')->name('fetch-data')->can('view-closed-items');
        Route::post('/{driving_force}/reopen', 'reopen')->name('reopen')->can('create-approval-items');
    });

    Route::prefix('user-management')->name('user-management.')->group(function () {
        Route::prefix('user')->controller(UserController::class)->name('user.')->group(function () {
            Route::get('/', 'index')->can('view-user');
            Route::get('/fetch-data', 'fetch_data')->name('fetch-data')->can('view-user');
            Route::post('/', 'create')->name('create')->can('create-user');
            Route::put('/{user}', 'update')->name('update')->can('edit-user');
            Route::delete('/{user}', 'delete')->name('delete')->can('delete-user');

            Route::prefix('profile')->controller(ProfileController::class)->name('profile.')->group(function () {
                Route::get('/', 'edit');
                Route::patch('/', 'update')->name('update');
            });
        });
        // Route::prefix('role')->controller(App\Http\Controllers\RoleController::class)->name('role.')->group(function () {
        //     Route::get('/', 'index');
        // });
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', function () {
            return redirect()->route('settings.llm.index');
        })->name('index');

        // AI & Reasoning Engine settings
        Route::prefix('ai')->name('ai.')->controller(UserLlmSettingController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('/activate', 'activate')->name('activate');
            Route::post('/test', 'test')->name('test');
            Route::delete('/{provider}', 'destroy')->name('destroy');
        });

        // Backward-compatible alias for llm endpoints
        Route::prefix('llm')->name('llm.')->controller(UserLlmSettingController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('/activate', 'activate')->name('activate');
            Route::post('/test', 'test')->name('test');
            Route::delete('/{provider}', 'destroy')->name('destroy');
        });

        // Tenant Workspace / Organization settings
        Route::prefix('organization')->name('organization.')->controller(OrganizationController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::patch('/', 'update')->name('update');
        });

        // Tenant Members & Team Invitations
        Route::prefix('members')->name('members.')->controller(TenantMemberController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/invite', 'invite')->name('invite');
            Route::patch('/{user}/role', 'updateRole')->name('role');
            Route::delete('/{user}', 'removeMember')->name('destroy');
            Route::delete('/invitations/{invitation}', 'cancelInvite')->name('invitations.cancel');
        });
    });

    Route::controller(ProfileController::class)->name('profile.')->group(function () {
        Route::get('/profile', 'edit')->name('edit');
        Route::patch('/profile', 'update')->name('update');
        Route::delete('/profile', 'destroy')->name('destroy');
    });
});

require __DIR__.'/auth.php';
