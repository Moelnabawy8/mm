<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\PublicRelations\Http\Controllers\PrAttachmentController;
use Modules\PublicRelations\Http\Controllers\PrEntityController;
use Modules\PublicRelations\Http\Controllers\PrFieldController;
use Modules\PublicRelations\Http\Controllers\PrInteractionLogController;
use Modules\PublicRelations\Http\Controllers\PrMilestoneController;

Route::prefix('pr')->middleware(['auth:sanctum'])->group(function () {
    // The main PR Sheet: organizations, filterable by type/status/date.
    Route::get('entities', [PrEntityController::class, 'index'])->name('pr.entities.index');
    Route::post('entities', [PrEntityController::class, 'store'])->name('pr.entities.store');
    Route::get('entities/{id}', [PrEntityController::class, 'show'])->name('pr.entities.show');
    Route::put('entities/{id}', [PrEntityController::class, 'update'])->name('pr.entities.update');
    Route::delete('entities/{id}', [PrEntityController::class, 'destroy'])->name('pr.entities.destroy');

    // Pipeline / interaction log rows for a single entity.
    Route::get('entities/{entity}/interaction-logs', [PrInteractionLogController::class, 'index'])->name('pr.interaction-logs.index');
    Route::post('entities/{entity}/interaction-logs', [PrInteractionLogController::class, 'store'])->name('pr.interaction-logs.store');
    Route::put('entities/{entity}/interaction-logs/{log}', [PrInteractionLogController::class, 'update'])->name('pr.interaction-logs.update');
    Route::delete('entities/{entity}/interaction-logs/{log}', [PrInteractionLogController::class, 'destroy'])->name('pr.interaction-logs.destroy');

    // Milestones (workshops, MoU / contract signings).
    Route::get('milestones', [PrMilestoneController::class, 'index'])->name('pr.milestones.index');
    Route::post('entities/{entity}/milestones', [PrMilestoneController::class, 'store'])->name('pr.milestones.store');
    Route::put('milestones/{milestone}', [PrMilestoneController::class, 'update'])->name('pr.milestones.update');
    Route::delete('milestones/{milestone}', [PrMilestoneController::class, 'destroy'])->name('pr.milestones.destroy');

    // Dynamic custom-field definitions (the customization layer).
    Route::get('fields', [PrFieldController::class, 'index'])->name('pr.fields.index');
    Route::post('fields', [PrFieldController::class, 'store'])->name('pr.fields.store');
    Route::put('fields/{field}', [PrFieldController::class, 'update'])->name('pr.fields.update');
    Route::delete('fields/{field}', [PrFieldController::class, 'destroy'])->name('pr.fields.destroy');

    // Attachments (call screenshots, proposals, draft contracts).
    Route::post('entities/{entity}/attachments', [PrAttachmentController::class, 'storeForEntity'])->name('pr.attachments.entity');
    Route::post('interaction-logs/{log}/attachments', [PrAttachmentController::class, 'storeForInteractionLog'])->name('pr.attachments.interaction-log');
    Route::post('milestones/{milestone}/attachments', [PrAttachmentController::class, 'storeForMilestone'])->name('pr.attachments.milestone');
    Route::delete('attachments/{attachment}', [PrAttachmentController::class, 'destroy'])->name('pr.attachments.destroy');
});
