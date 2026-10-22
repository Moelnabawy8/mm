<?php

use App\Http\Controllers\GoogleMeetController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth', 'XSS']], function () {
    Route::get('/google-meet', [GoogleMeetController::class, 'index'])->name('google-meet.index');
    Route::get('/google-meet/create', [GoogleMeetController::class, 'create'])->name('google-meet.create');
    Route::post('/google-meet/store', [GoogleMeetController::class, 'store'])->name('google-meet.store');
    Route::get('/google-meet/edit/{id}', [GoogleMeetController::class, 'edit'])->name('google-meet.edit');
    Route::post('/google-meet/update/{id}', [GoogleMeetController::class, 'update'])->name('google-meet.update');
    Route::delete('/google-meet/delete/{id}', [GoogleMeetController::class, 'destroy'])->name('google-meet.destroy');
    Route::post('/google-meet/finish/{id}', [GoogleMeetController::class, 'finish'])->name('google-meet.finish');
});