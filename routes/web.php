<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForesightRadarController;
use App\Http\Controllers\PrioritizingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegisteredListController;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

Route::prefix("/")->middleware(['auth', 'verified'])->group(function () {
    Route::prefix("/dashboard")->controller(App\Http\Controllers\DashboardController::class)->name('dashboard.')->group(function () {
        Route::get("/", "index")->can('view-dashboard');
    });

    Route::prefix('visualization')->name('visualization.')->group(function() {
        Route::prefix("/prioritizing")->name('prioritizing.')->group(function() {
            Route::get("/", [PrioritizingController::class, 'index']);
            Route::get("/get-data", [PrioritizingController::class, 'prioritizing'])->name('get-data');
        });
        Route::prefix("/registered-list")->name('registered-list.')->group(function() {
            Route::get("/", [RegisteredListController::class, 'index']);
            Route::get("/get-data", [RegisteredListController::class, 'registered_list'])->name('get-data');
            Route::get('/export-data', [RegisteredListController::class, 'export_data'])->name('export-data');
        });
        Route::prefix("/foresight-radar")->name('foresight-radar.')->group(function() {
            Route::get("/", [ForesightRadarController::class, 'index']);
            Route::get("/get-data", [ForesightRadarController::class, 'foresight_radar'])->name('get-data');
        });
    });


    Route::prefix("driving-force")->controller(App\Http\Controllers\DrivingForceController::class)->name("driving-force.")->group(function () {
        Route::get("/", "index")->can('view-driving-force');
        Route::get("/fetch-data", "fetch_data")->name('fetch-data')->can('view-driving-force');
        Route::post("/", "create")->name('create');
        Route::put("/{driving_force}", "update")->name('update');
        Route::delete("/{driving_force}", "delete")->name('delete');
    });

    Route::prefix("time-horizon")->controller(App\Http\Controllers\TimeHorizonController::class)->name("time-horizon.")->group(function () {
        Route::get("/", "index")->can('view-time-horizon');
        Route::get("/fetch-data", "fetch_data")->name('fetch-data')->can('view-time-horizon');
        Route::post("/{driving_force:id}", "create")->name('create');
    });

    Route::prefix("rating-urgency")->controller(App\Http\Controllers\RatingUrgencyController::class)->name("rating-urgency.")->group(function () {
        Route::get("/", "index")->can('view-rating-urgency');
        Route::get("/fetch-data", "fetch_data")->name('fetch-data')->can('view-rating-urgency');
        Route::post("/{driving_force_rating}", "create")->name('create');
    });

    Route::prefix("status-action")->controller(App\Http\Controllers\StatusActionController::class)->name("status-action.")->group(function () {
        Route::get("/", "index")->can('view-status-action');
        Route::get("/fetch-data", "fetch_data")->name('fetch-data')->can('view-status-action');
        Route::post("/{driving_force_rating}", "create")->name('create');
    });

    Route::prefix("approval-items")->controller(App\Http\Controllers\ApprovalController::class)->name("approval-items.")->group(function () {
        Route::get("/", "index")->can('view-approval-items');
        Route::get("/fetch-data", "fetch_data")->name('fetch-data')->can('view-approval-items');
        Route::post("/{driving_force}", "create")->can('create-approval-items')->name('create');
    });

    Route::prefix("closed-items")->controller(App\Http\Controllers\ClosedItemsController::class)->name("closed-items.")->group(function () {
        Route::get("/", "index")->can('view-closed-items');
        Route::get("/fetch-data", "fetch_data")->name('fetch-data')->can('view-closed-items');
    });

    Route::prefix('user-management')->name('user-management.')->group(function () {
        Route::prefix('user')->controller(App\Http\Controllers\UserController::class)->name('user.')->group(function () {
            Route::get('/', 'index')->can('view-user');
            Route::get("/fetch-data", "fetch_data")->name('fetch-data')->can('view-user');
            Route::post("/", "create")->name('create');
            Route::put("/{user}", "update")->name('update');
            Route::delete("/{user}", "delete")->name('delete');

            Route::prefix('profile')->controller(App\Http\Controllers\ProfileController::class)->name('profile.')->group(function () {
                Route::get('/', 'edit');
                Route::patch('/', 'update')->name('update');
            });
        });
        // Route::prefix('role')->controller(App\Http\Controllers\RoleController::class)->name('role.')->group(function () {
        //     Route::get('/', 'index');
        // });
    });
});

require __DIR__ . '/auth.php';
