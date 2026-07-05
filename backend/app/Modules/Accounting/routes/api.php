<?php

use App\Modules\Accounting\Http\Controllers\EntryController;
use App\Modules\Accounting\Http\Controllers\InvoiceController;
use App\Modules\Accounting\Http\Controllers\PeriodController;
use App\Modules\Accounting\Http\Controllers\ReferentialController;
use Illuminate\Support\Facades\Route;

/**
 * RC-23 — routes du module Comptabilité (référentiel).
 *
 * Gating : auth + appartenance tenant + module `accounting` actif dans l'abonnement.
 * Lecture : rôles comptables (+ admin/manager) ou permission accounting.view.
 * Écriture : chef comptable / admin ou permission accounting.manage.
 */
Route::middleware(['auth:sanctum', \App\Modules\Auth\Http\Middleware\EnsureUserBelongsToTenant::class, 'module:accounting'])
    ->prefix('api/accounting')
    ->group(function () {

        Route::middleware('role_or_permission:accountant|chief-accountant|accounting-viewer|auditor|admin|manager|accounting.view')->group(function () {
            Route::get('overview',      [ReferentialController::class, 'overview']);
            Route::get('accounts',      [ReferentialController::class, 'accounts']);
            Route::get('journals',      [ReferentialController::class, 'journals']);
            Route::get('taxes',         [ReferentialController::class, 'taxes']);
            Route::get('settings',      [ReferentialController::class, 'settings']);
            Route::get('fiscal-years',  [PeriodController::class, 'index']);
            // Écritures — lecture (RC-25)
            Route::get('entries',       [EntryController::class, 'index']);
            Route::get('entries/{id}',  [EntryController::class, 'show']);
            // Factures — lecture (RC-30)
            Route::get('invoices',          [InvoiceController::class, 'index']);
            Route::get('invoices/{id}',     [InvoiceController::class, 'show']);
            Route::get('invoices/{id}/pdf', [InvoiceController::class, 'pdf']);
        });

        Route::middleware('role_or_permission:chief-accountant|admin|accounting.manage')->group(function () {
            Route::post('provision',            [ReferentialController::class, 'provision']);
            Route::post('accounts',             [ReferentialController::class, 'storeAccount']);
            Route::put('accounts/{id}',         [ReferentialController::class, 'updateAccount']);
            Route::put('journals/{id}',         [ReferentialController::class, 'updateJournal']);
            Route::post('taxes',                [ReferentialController::class, 'storeTax']);
            Route::put('taxes/{id}',            [ReferentialController::class, 'updateTax']);
            Route::put('settings',              [ReferentialController::class, 'updateSettings']);
            Route::post('periods/{id}/lock',    [PeriodController::class, 'lock']);
        });

        // Écritures — saisie (RC-25 ; la création exige accounting.entries.create).
        Route::middleware('role_or_permission:accountant|chief-accountant|admin|accounting.entries.create')->group(function () {
            Route::post('entries', [EntryController::class, 'store']);
            // Factures — saisie/émission/allocation (RC-30).
            Route::post('invoices',                    [InvoiceController::class, 'store']);
            Route::post('invoices/from-order/{orderId}', [InvoiceController::class, 'fromOrder']);
            Route::post('invoices/{id}/issue',         [InvoiceController::class, 'issue']);
            Route::post('invoices/{id}/payments',      [InvoiceController::class, 'allocate']);
            // Avoirs — création depuis facture, émission, application (RC-33).
            Route::post('invoices/{id}/credit-notes',  [InvoiceController::class, 'creditNoteFromInvoice']);
            Route::post('credit-notes/{id}/issue',     [InvoiceController::class, 'issueCreditNote']);
            Route::post('credit-notes/{id}/apply',     [InvoiceController::class, 'applyCreditNote']);
        });
        // Comptabilisation / extourne : chef comptable / admin (permissions dédiées).
        Route::middleware('role_or_permission:chief-accountant|admin|accounting.entries.post')->group(function () {
            Route::post('entries/{id}/post', [EntryController::class, 'post']);
        });
        Route::middleware('role_or_permission:chief-accountant|admin|accounting.entries.reverse')->group(function () {
            Route::post('entries/{id}/reverse', [EntryController::class, 'reverse']);
        });

        // Réouverture d'une période : permission DÉDIÉE (séparation des pouvoirs).
        Route::middleware('role_or_permission:admin|accounting.periods.reopen')->group(function () {
            Route::post('periods/{id}/unlock', [PeriodController::class, 'unlock']);
        });
    });
