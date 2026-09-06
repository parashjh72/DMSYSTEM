<?php

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\Dashboard;
use App\Livewire\DataExplorer;
use App\Livewire\ExportManager;
use App\Livewire\ImeiSearch;
use App\Livewire\ImportDetail;
use App\Livewire\ImportManager;
use App\Livewire\MasterData;
use App\Livewire\Reports;
use App\Livewire\UserManager;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', Dashboard::class)->middleware('can:dashboard.view')->name('dashboard');

    Route::get('imports', ImportManager::class)->middleware('can:imports.view')->name('imports.index');
    Route::get('imports/{batch}', ImportDetail::class)->middleware('can:imports.view')->name('imports.show');

    Route::get('explorer', DataExplorer::class)->middleware('can:explorer.view')->name('explorer');
    Route::get('reports', Reports::class)->middleware('can:reports.view')->name('reports');
    Route::get('imei-search', ImeiSearch::class)->middleware('can:reports.view')->name('imei-search');

    Route::get('exports', ExportManager::class)->middleware('can:exports.view')->name('exports.index');

    Route::get('master-data', MasterData::class)->middleware('can:masterdata.view')->name('masterdata');
    Route::get('users', UserManager::class)->middleware('can:users.manage')->name('users.index');
});
