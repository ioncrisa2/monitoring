<?php

use App\Http\Controllers\OfferDocumentController;
use App\Livewire\Audit\ActivityLogIndex;
use App\Livewire\Dashboard;
use App\Livewire\Imports\DataImport;
use App\Livewire\Master\Branches;
use App\Livewire\Master\Debtors;
use App\Livewire\Master\OfferDocumentMasters;
use App\Livewire\Master\Organizations;
use App\Livewire\Master\RolesPermissions;
use App\Livewire\Master\Users;
use App\Livewire\Offers\Create as OffersCreate;
use App\Livewire\Offers\DocumentEditor as OfferDocumentEditor;
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
    Route::get('/offers/create', OffersCreate::class)->middleware('permission:menu.offers')->name('offers.create');
    Route::get('/offers/{offer}/document', OfferDocumentEditor::class)
        ->middleware('permission:offers.documents.view')
        ->name('offers.documents.edit');
    Route::get('/offers/{offer}/document/preview', [OfferDocumentController::class, 'preview'])
        ->middleware(['permission:offers.documents.generate-draft', 'throttle:10,1'])
        ->name('offers.documents.preview');
    Route::get('/offers/{offer}/document/download', [OfferDocumentController::class, 'download'])
        ->middleware(['permission:offers.documents.generate-draft', 'throttle:10,1'])
        ->name('offers.documents.download');
    Route::post('/offers/{offer}/document/submit', [OfferDocumentController::class, 'submit'])
        ->middleware(['permission:offers.documents.manage', 'throttle:10,1'])
        ->name('offers.documents.submit');
    Route::post('/offers/{offer}/document/versions/{version}/approve', [OfferDocumentController::class, 'approve'])
        ->middleware(['permission:offers.documents.generate-print-ready', 'throttle:10,1'])
        ->name('offers.documents.approve');
    Route::post('/offers/{offer}/document/versions/{version}/reject', [OfferDocumentController::class, 'reject'])
        ->middleware(['permission:offers.documents.generate-print-ready', 'throttle:10,1'])
        ->name('offers.documents.reject');
    Route::post('/offers/{offer}/document/versions/{version}/finalize', [OfferDocumentController::class, 'finalize'])
        ->middleware(['permission:offers.documents.generate-print-ready', 'throttle:10,1'])
        ->name('offers.documents.finalize');
    Route::get('/offers/{offer}/document/print-ready', [OfferDocumentController::class, 'printReady'])
        ->middleware(['permission:offers.documents.generate-print-ready', 'throttle:10,1'])
        ->name('offers.documents.print-ready');
    Route::get(
        '/offers/{offer}/document/versions/{version}/artifacts/{artifact}',
        [OfferDocumentController::class, 'artifact'],
    )
        ->middleware(['permission:offers.documents.view', 'throttle:20,1'])
        ->name('offers.documents.artifacts.download');
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
        Route::get('/roles-permissions', RolesPermissions::class)->middleware('permission:users.manage')->name('roles-permissions');
        Route::get('/organizations', Organizations::class)->middleware('permission:menu.master-data')->name('organizations');
        Route::get('/debtors', Debtors::class)->middleware('permission:menu.master-data')->name('debtors');
        Route::get('/offer-documents', OfferDocumentMasters::class)
            ->middleware('permission:offers.document-masters.view')
            ->name('offer-documents');
    });
});

require __DIR__.'/auth.php';
