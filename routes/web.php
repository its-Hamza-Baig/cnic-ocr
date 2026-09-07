<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonImageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/persons', [PersonController::class, 'index'])->name('persons.index');
    Route::get('/persons/create', [PersonController::class, 'create'])->name('persons.create');
    Route::post('/persons/ocr', [PersonController::class, 'ocr'])
        ->middleware('throttle:ocr')
        ->name('persons.ocr');
    Route::get('/persons/create/preview/{side}', [PersonController::class, 'previewImage'])
        ->name('persons.preview');
    Route::post('/persons/check-duplicate', [PersonController::class, 'checkDuplicate'])
        ->middleware('throttle:cnic-sensitive')
        ->name('persons.check-duplicate');
    Route::post('/persons/discard', [PersonController::class, 'discard'])->name('persons.discard');
    Route::post('/persons', [PersonController::class, 'store'])
        ->middleware('throttle:cnic-sensitive')
        ->name('persons.store');
    Route::get('/persons/{person}', [PersonController::class, 'show'])->name('persons.show');
    Route::get('/persons/{person}/edit', [PersonController::class, 'edit'])->name('persons.edit');
    Route::put('/persons/{person}', [PersonController::class, 'update'])
        ->middleware('throttle:cnic-sensitive')
        ->name('persons.update');
    Route::patch('/persons/{person}', [PersonController::class, 'update']);
    Route::delete('/persons/{person}', [PersonController::class, 'destroy'])->name('persons.destroy');
    Route::get('/persons/{person}/image/{side}', PersonImageController::class)
        ->name('persons.image');
});
