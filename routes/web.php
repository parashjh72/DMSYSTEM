<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\ImportTemplateController;
use App\Livewire\Attendance;
use App\Livewire\AttendanceReport;
use App\Livewire\Dashboard;
use App\Livewire\DataExplorer;
use App\Livewire\ExportManager;
use App\Livewire\ImeiSearch;
use App\Livewire\ImportDetail;
use App\Livewire\ImportManager;
use App\Livewire\MailSettings;
use App\Livewire\MapSettings;
use App\Livewire\MasterData;
use App\Livewire\ModelPrices;
use App\Livewire\Pjp;
use App\Livewire\Promoters;
use App\Livewire\QuickReports;
use App\Livewire\Reports;
use App\Livewire\RetailerMap;
use App\Livewire\Returns;
use App\Livewire\ScheduledReports;
use App\Livewire\SchemeEnrolment;
use App\Livewire\SchemeReport;
use App\Livewire\SchemeRetailers;
use App\Livewire\Schemes;
use App\Livewire\SelloutReport;
use App\Livewire\Settings;
use App\Livewire\StockReport;
use App\Livewire\Transfer;
use App\Livewire\UserManager;
use App\Livewire\WodCoverage;
use App\Support\Home;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Home::route()));

// HTTP scheduler trigger for curl-based cron. Enabled only when CRON_TOKEN is set.
Route::get('cron/{token}', CronController::class)->name('cron');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login']);

    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:6,1')->name('password.update');
});

Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', Dashboard::class)->middleware('can:dashboard.view')->name('dashboard');

    Route::get('attendance', Attendance::class)->middleware('can:attendance.self')->name('attendance');
    Route::get('attendance/report', AttendanceReport::class)->middleware('can:attendance.view_all')->name('attendance.report');
    Route::get('pjp', Pjp::class)->middleware('can:pjp.access')->name('pjp');

    Route::get('imports', ImportManager::class)->middleware('can:imports.access')->name('imports.index');
    Route::get('imports/template', ImportTemplateController::class)->middleware('can:imports.access')->name('imports.template');
    Route::get('imports/{batch}', ImportDetail::class)->middleware('can:imports.access')->name('imports.show');

    Route::get('explorer', DataExplorer::class)->middleware('can:explorer.view')->name('explorer');
    Route::get('reports', Reports::class)->middleware('can:reports.view')->name('reports');
    Route::get('stock-report', StockReport::class)->middleware('can:reports.view')->name('stock');
    Route::get('sellout-report', SelloutReport::class)->middleware('can:reports.view')->name('sellout');
    Route::get('quick-reports', QuickReports::class)->middleware('can:reports.view')->name('quick-reports');
    Route::get('wod-coverage', WodCoverage::class)->middleware('can:reports.view')->name('wod-coverage');
    Route::get('scheduled-reports', ScheduledReports::class)->middleware('can:scheduled-reports.manage')->name('scheduled-reports');
    Route::get('returns', Returns::class)->middleware('can:returns.access')->name('returns');
    Route::get('promoters', Promoters::class)->middleware('can:promoters.access')->name('promoters');
    Route::get('model-prices', ModelPrices::class)->middleware('can:masterdata.view')->name('model-prices');

    Route::get('schemes', Schemes::class)->middleware('can:settings.manage')->name('schemes.index');
    Route::get('scheme-enrolment', SchemeEnrolment::class)->middleware('can:schemes.enrol')->name('scheme-enrolment');
    Route::get('schemes/{scheme}/retailers', SchemeRetailers::class)->middleware('can:schemes.enrol')->name('schemes.retailers');
    Route::get('schemes/{scheme}/achievement', SchemeReport::class)->middleware('can:reports.view')->name('schemes.report');
    Route::get('imei-search', ImeiSearch::class)->middleware('can:reports.view')->name('imei-search');
    Route::get('retailer-map', RetailerMap::class)->middleware('can:reports.view')->name('retailer-map');

    Route::get('exports', ExportManager::class)->middleware('can:exports.view')->name('exports.index');

    Route::get('master-data', MasterData::class)->middleware('can:masterdata.view')->name('masterdata');

    Route::middleware('can:settings.manage')->group(function () {
        Route::get('settings', Settings::class)->name('settings.index');
        Route::get('settings/transfer', Transfer::class)->name('settings.transfer');
        Route::get('settings/mail', MailSettings::class)->name('settings.mail');
        Route::get('settings/maps', MapSettings::class)->name('settings.maps');
    });

    Route::get('users', UserManager::class)->middleware('can:users.manage')->name('users.index');
});
