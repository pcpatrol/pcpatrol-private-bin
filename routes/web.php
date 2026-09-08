<?php

use App\Http\Controllers\PasteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PasteController::class, 'create'])->name('home');

Route::post('p', [PasteController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('paste.store');

Route::get('p/{paste}', [PasteController::class, 'show'])
    ->whereAlphaNumeric('paste')
    ->name('paste.show');

Route::post('p/{paste}/reveal', [PasteController::class, 'reveal'])
    ->whereAlphaNumeric('paste')
    ->middleware('throttle:30,1')
    ->name('paste.reveal');

Route::get('p/{paste}/delete/{token}', [PasteController::class, 'confirmDelete'])
    ->whereAlphaNumeric('paste')
    ->whereAlphaNumeric('token')
    ->name('paste.delete');

Route::delete('p/{paste}', [PasteController::class, 'destroy'])
    ->whereAlphaNumeric('paste')
    ->name('paste.destroy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
