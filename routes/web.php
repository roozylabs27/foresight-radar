<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
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
        Route::get("/", "index");
    });

    Route::get("/prioritizing", [DashboardController::class, 'prioritizing'])->name('prioritizing');

    Route::prefix("driving-force")->controller(App\Http\Controllers\DrivingForceController::class)->name("driving-force.")->group(function() {
        Route::get("/", "index");
        Route::post("/", "create")->name('create');
        Route::get("/fetch-data", "fetch_data")->name('fetch-data');
        Route::put("/{driving_force}", "update")->name('update');
        Route::delete("/{driving_force}", "delete")->name('delete');
    });

    Route::prefix("time-horizon")->controller(App\Http\Controllers\TimeHorizonController::class)->name("time-horizon.")->group(function() {
        Route::get("/", "index");
        Route::get("/fetch-data", "fetch_data")->name('fetch-data');
        Route::post("/{driving_force:id}", "create")->name('create');
    });

    Route::prefix("rating-urgency")->controller(App\Http\Controllers\RatingUrgencyController::class)->name("rating-urgency.")->group(function() {
        Route::get("/", "index");
        Route::get("/fetch-data", "fetch_data")->name('fetch-data');
        Route::post("/{driving_force_rating}", "create")->name('create');
    });

    Route::prefix("status-action")->controller(App\Http\Controllers\StatusActionController::class)->name("status-action.")->group(function() {
        Route::get("/", "index");
        Route::get("/fetch-data", "fetch_data")->name('fetch-data');
        Route::post("/{driving_force_rating}", "create")->name('create');
    });

    Route::prefix('profile')->controller(App\Http\Controllers\ProfileController::class)->name('profile.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::patch('/', 'update')->name('update');
        Route::delete('/', 'destroy')->name('destroy');
    });

    Route::prefix('user-management')->name('user-management.')->group(function () {
        Route::prefix('user')->controller(App\Http\Controllers\UserController::class)->name('user.')->group(function () {
            Route::get('/', 'index');
        });
        Route::prefix('role')->controller(App\Http\Controllers\RoleController::class)->name('role.')->group(function () {
            Route::get('/', 'index');
        });
    });
});

require __DIR__ . '/auth.php';
