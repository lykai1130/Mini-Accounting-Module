<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\TrialBalanceReportController;
use Illuminate\Support\Facades\Route;

Route::get('/accounts', [AccountController::class, 'index']);
Route::get('/journal-entries', [JournalEntryController::class, 'index']);
Route::post('/journal-entries', [JournalEntryController::class, 'store']);
Route::delete('/journal-entries/{journalEntry}', [JournalEntryController::class, 'destroy']);
Route::get('/reports/trial-balance', [TrialBalanceReportController::class, 'index']);
