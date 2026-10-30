<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\AdminLayoutMiddleware;
use Modules\Quality\Http\Controllers\QualityController;

Route::group([
    'prefix' => config('theme.admin_route_prefix', 'admin'),
    'as' => 'admin::',
    'middleware' => [
        AdminLayoutMiddleware::class,
        'admin.check',
    ],
], function () {
    Route::prefix('quality')->as('quality.')->group(function () {
        Route::middleware('quality.role:purchase,incoming')->group(function () {
            Route::livewire('analysis', 'admin.quality.analysis-page')->name('analysis');
            Route::livewire('alerts', 'admin.quality.alerts-page')->name('alerts');
        });

        Route::middleware('quality.role:purchase')->prefix('purchase')->as('purchase.')->group(function () {
            Route::livewire('orders', 'admin.quality.purchase.orders-page')->name('orders');
            Route::livewire('orders/create', 'admin.quality.purchase.order-form-page')->name('orders.create');
            Route::livewire('orders/{order}', 'admin.quality.purchase.order-show-page')->name('orders.show');
            Route::livewire('orders/{order}/edit', 'admin.quality.purchase.order-form-page')->name('orders.edit');
            Route::get('orders/{order}/format', [QualityController::class, 'purchaseOrder'])->name('orders.format');

            Route::livewire('invoices', 'admin.quality.purchase.invoices-page')->name('invoices');
            Route::livewire('invoices/create', 'admin.quality.purchase.invoice-form-page')->name('invoices.create');
            Route::livewire('invoices/{invoice}', 'admin.quality.purchase.invoice-show-page')->name('invoices.show');
            Route::livewire('invoices/{invoice}/edit', 'admin.quality.purchase.invoice-form-page')->name('invoices.edit');
            Route::get('invoices/{invoice}/format', [QualityController::class, 'purchaseInvoice'])->name('invoices.format');
        });

        Route::middleware('module.access:quality.incoming.vendors')->prefix('incoming')->as('incoming.')->group(function () {
            Route::livewire('vendors', 'admin.quality.incoming.vendors-page')->name('vendors');
        });

        Route::middleware('module.access:quality.incoming.categories')->prefix('incoming')->as('incoming.')->group(function () {
            Route::livewire('categories', 'admin.quality.incoming.categories-page')->name('categories');
        });

        Route::middleware('quality.role:incoming')->prefix('incoming')->as('incoming.')->group(function () {
            Route::livewire('parts', 'admin.quality.incoming.parts-page')->name('parts');
            Route::livewire('parts/{part}/standard', 'admin.quality.incoming.part-standard-page')->name('parts.standard');
            Route::get('parts/{part}/standard/document', [QualityController::class, 'partStandardDocument'])->name('parts.standard.document');
            Route::get('parts/{part}/standard/pdf', [QualityController::class, 'partStandardPdf'])->name('parts.standard.pdf');
            Route::get('parts/{part}/standard/excel', [QualityController::class, 'partStandardExcel'])->name('parts.standard.excel');
            Route::livewire('sampling-plans', 'admin.quality.incoming.sampling-plans-page')->name('sampling');
            Route::livewire('reports', 'admin.quality.incoming.reports-page')->name('reports');
            Route::livewire('reports/create', 'admin.quality.incoming.report-form-page')->name('reports.create');
            Route::livewire('reports/{report}/edit', 'admin.quality.incoming.report-form-page')->name('reports.edit');
            Route::get('reports/{report}/pdf', [QualityController::class, 'reportPdf'])->name('reports.pdf');
            Route::get('reports/{report}/excel', [QualityController::class, 'reportExcel'])->name('reports.excel');
            Route::livewire('reports/{report}', 'admin.quality.incoming.report-show-page')->name('reports.show');
        });
    });
});
