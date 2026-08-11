<?php

use App\Livewire\Audit\ActivityLogIndex;
use App\Livewire\Dashboard;
use App\Livewire\Imports\DataImport;
use App\Livewire\Master\Branches;
use App\Livewire\Master\Debtors;
use App\Livewire\Master\Organizations;
use App\Livewire\Master\RolesPermissions;
use App\Livewire\Master\Users;
use App\Livewire\Offers\Index as OffersIndex;
use App\Livewire\Reports\ProductionReport;
use App\Livewire\WorkOrders\Index as WorkOrdersIndex;
use App\Livewire\WorkOrders\Show as WorkOrdersShow;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('dashboard', Dashboard::class)
    ->middleware(['auth', 'verified', 'permission:menu.dashboard'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::view('profile', 'profile')->name('profile');

    // Penawaran & Pekerjaan Workflow
    Route::get('/offers', OffersIndex::class)->middleware('permission:menu.offers')->name('offers.index');
    Route::get('/work-orders', WorkOrdersIndex::class)->middleware('permission:menu.work-orders')->name('work-orders.index');
    Route::get('/work-orders/{id}', WorkOrdersShow::class)->middleware('permission:menu.work-orders')->name('work-orders.show');

    // Laporan & Export Center
    Route::get('/reports/production', ProductionReport::class)->middleware('permission:menu.reports')->name('reports.production');

    // Impor Data & Migrasi Historis
    Route::get('/imports', DataImport::class)->middleware('permission:menu.imports')->name('imports.index');

    // Audit Trail & System Log
    Route::get('/audit-logs', ActivityLogIndex::class)->middleware('permission:menu.audit-logs')->name('audit-logs.index');

    // Master Data
    Route::prefix('master')->name('master.')->group(function () {
        Route::get('/branches', Branches::class)->middleware('permission:menu.master-data')->name('branches');
        Route::get('/users', Users::class)->middleware('permission:menu.master-users')->name('users');
        Route::get('/roles-permissions', RolesPermissions::class)->middleware('permission:menu.master-users')->name('roles-permissions');
        Route::get('/organizations', Organizations::class)->middleware('permission:menu.master-data')->name('organizations');
        Route::get('/debtors', Debtors::class)->middleware('permission:menu.master-data')->name('debtors');
    });
});

require __DIR__.'/auth.php';
