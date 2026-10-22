<?php

use App\Http\Controllers\SheetController;
use Illuminate\Support\Facades\Route;

Route::prefix("google-sheet")->middleware(['auth', 'XSS'])->group(function () {
    Route::get('/', [SheetController::class, 'index'])->name("google-sheet.index");
    Route::get('/{id}', [SheetController::class, 'show'])->name("google-sheet.show")->whereNumber('id');
    Route::post('/{id}/save', [SheetController::class, 'save'])->name("google-sheet.save")->whereNumber('id');
    Route::post('/create', [SheetController::class, 'create'])->name("google-sheet.create");
    Route::delete('/delete/{id}', [SheetController::class, 'delete'])->name("google-sheet.delete")->whereNumber('id');
});