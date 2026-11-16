<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\SlipController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated Application Routes
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('tasks.index');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('password.change');

    // Module 1: Task Hub & Matrix
    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/', [TaskController::class, 'index'])->name('index');
        Route::post('/', [TaskController::class, 'store'])->name('store');
        Route::get('/{task}', [TaskController::class, 'show'])->name('show');
        Route::post('/{task}/cell', [TaskController::class, 'updateCell'])->name('cell.update');
        Route::post('/{task}/multiplier', [TaskController::class, 'updateMultiplier'])->name('multiplier.update');
        Route::post('/{task}/auto-sundays', [TaskController::class, 'autoMarkSundays'])->name('auto_sundays');
        Route::post('/{task}/policy/{record}', [TaskController::class, 'togglePolicy'])->name('policy.toggle');
        Route::post('/{task}/reference-outlet/{record}', [TaskController::class, 'updateReferenceOutlet'])->name('reference_outlet.update');
        Route::post('/{task}/twin-mode', [TaskController::class, 'toggleTwinOutletMode'])->name('twin_mode.update');
        Route::post('/{task}/manual/{recordId}', [TaskController::class, 'updateManualAddition'])->name('manual.update');
        Route::post('/{task}/finish', [TaskController::class, 'finish'])->name('finish');
        Route::post('/{task}/reopen', [TaskController::class, 'reopen'])->name('reopen');
        Route::delete('/{task}', [TaskController::class, 'destroy'])->name('destroy');

        // Module 3: Upload & Validation
        Route::post('/{task}/upload', [UploadController::class, 'upload'])->name('upload');
        Route::get('/{task}/upload/validation', [UploadController::class, 'showValidation'])->name('upload.validation');
        Route::post('/{task}/upload/commit', [UploadController::class, 'commitValidation'])->name('upload.commit');
    });

    // Slips
    Route::get('/slips/{record}', [SlipController::class, 'show'])->name('slips.show');

    // Module 2: Master Data & Configurable Constants
    Route::prefix('master')->name('master.')->group(function () {
        Route::get('/', [MasterDataController::class, 'index'])->name('index');

        // Outlets
        Route::post('/outlets', [MasterDataController::class, 'storeOutlet'])->name('outlets.store');
        Route::put('/outlets/{outlet}', [MasterDataController::class, 'updateOutlet'])->name('outlets.update');
        Route::post('/outlets/{outlet}/toggle', [MasterDataController::class, 'toggleOutlet'])->name('outlets.toggle');

        // Aliases
        Route::post('/aliases', [MasterDataController::class, 'storeAlias'])->name('aliases.store');
        Route::delete('/aliases/{alias}', [MasterDataController::class, 'deleteAlias'])->name('aliases.delete');

        // SPGs
        Route::post('/spgs', [MasterDataController::class, 'storeSpg'])->name('spgs.store');
        Route::put('/spgs/{spg}', [MasterDataController::class, 'updateSpg'])->name('spgs.update');
        Route::post('/spgs/{spg}/toggle', [MasterDataController::class, 'toggleSpg'])->name('spgs.toggle');

        // Incentive Settings
        Route::post('/incentive-settings', [MasterDataController::class, 'updateIncentiveSettings'])->name('incentive.update');
    });

    // Module 5: Executive Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // Exports
    Route::prefix('export')->name('export.')->group(function () {
        Route::get('/salary/{task}', [ExportController::class, 'exportSalary'])->name('salary');
        Route::get('/incentive/{task}', [ExportController::class, 'exportIncentive'])->name('incentive');
        Route::get('/annual', [ExportController::class, 'exportAnnual'])->name('annual');
        Route::get('/template/{task}', [ExportController::class, 'downloadTemplate'])->name('template');
    });
});
