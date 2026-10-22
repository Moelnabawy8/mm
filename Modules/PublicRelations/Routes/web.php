<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\PublicRelations\Http\Controllers\PrAttachmentWebController;
use Modules\PublicRelations\Http\Controllers\PrEntityWebController;
use Modules\PublicRelations\Http\Controllers\PrFieldWebController;
use Modules\PublicRelations\Http\Controllers\PrInteractionLogWebController;
use Modules\PublicRelations\Http\Controllers\PrMilestoneWebController;

Route::prefix('pr')->middleware(['web', 'auth'])->group(function () {
    // Custom field definitions (must be registered before the {id} wildcard routes below).
    // Named in the singular ("pr.field.*") to avoid colliding with the plural
    // "pr.fields.*" names already used by the JSON API routes (Routes/api.php).
    Route::get('fields', [PrFieldWebController::class, 'index'])->name('pr.field.index');
    Route::get('fields/create', [PrFieldWebController::class, 'create'])->middleware('XSS')->name('pr.field.create');
    Route::post('fields', [PrFieldWebController::class, 'store'])->middleware('XSS')->name('pr.field.store');
    Route::get('fields/{field}/edit', [PrFieldWebController::class, 'edit'])->middleware('XSS')->name('pr.field.edit');
    Route::put('fields/{field}', [PrFieldWebController::class, 'update'])->middleware('XSS')->name('pr.field.update');
    Route::delete('fields/{field}', [PrFieldWebController::class, 'destroy'])->name('pr.field.destroy');

    // Milestones addressed by their own id (not nested under an entity segment).
    // Named in the singular ("pr.milestone.*") for the same reason as fields above.
    Route::get('milestones/{milestone}/edit', [PrMilestoneWebController::class, 'edit'])->middleware('XSS')->name('pr.milestones.edit');
    Route::put('milestones/{milestone}', [PrMilestoneWebController::class, 'update'])->middleware('XSS')->name('pr.milestone.update');
    Route::delete('milestones/{milestone}', [PrMilestoneWebController::class, 'destroy'])->name('pr.milestone.destroy');

    // The main PR Sheet.
    Route::get('/', [PrEntityWebController::class, 'index'])->name('pr.index');
    Route::get('create', [PrEntityWebController::class, 'create'])->middleware('XSS')->name('pr.create');
    Route::post('/', [PrEntityWebController::class, 'store'])->middleware('XSS')->name('pr.store');
    Route::get('{id}/edit', [PrEntityWebController::class, 'edit'])->middleware('XSS')->name('pr.edit');
    Route::put('{id}', [PrEntityWebController::class, 'update'])->middleware('XSS')->name('pr.update');
    Route::delete('{id}', [PrEntityWebController::class, 'destroy'])->name('pr.destroy');
    Route::get('{id}', [PrEntityWebController::class, 'show'])->name('pr.show');

    // Interaction logs (pipeline rows) nested under an entity.
    Route::get('{entity}/interaction-logs/create', [PrInteractionLogWebController::class, 'create'])->middleware('XSS')->name('pr.logs.create');
    Route::post('{entity}/interaction-logs', [PrInteractionLogWebController::class, 'store'])->middleware('XSS')->name('pr.logs.store');
    Route::get('{entity}/interaction-logs/{log}/edit', [PrInteractionLogWebController::class, 'edit'])->middleware('XSS')->name('pr.logs.edit');
    Route::put('{entity}/interaction-logs/{log}', [PrInteractionLogWebController::class, 'update'])->middleware('XSS')->name('pr.logs.update');
    Route::delete('{entity}/interaction-logs/{log}', [PrInteractionLogWebController::class, 'destroy'])->name('pr.logs.destroy');

    // Milestones nested under an entity (create only — edit/update/destroy are above).
    Route::get('{entity}/milestones/create', [PrMilestoneWebController::class, 'create'])->middleware('XSS')->name('pr.milestones.create');
    Route::post('{entity}/milestones', [PrMilestoneWebController::class, 'store'])->middleware('XSS')->name('pr.milestone.store');

    // Attachments nested under an entity. "pr.attachment.destroy" (singular) to
    // avoid colliding with the API's "pr.attachments.destroy".
    Route::post('{entity}/attachments', [PrAttachmentWebController::class, 'upload'])->middleware('XSS')->name('pr.attachments.upload');
    Route::get('{entity}/attachments/{attachment}/download', [PrAttachmentWebController::class, 'download'])->name('pr.attachments.download');
    Route::get('{entity}/attachments/{attachment}/serve', [PrAttachmentWebController::class, 'serve'])->name('pr.attachments.serve');
    Route::delete('{entity}/attachments/{attachment}', [PrAttachmentWebController::class, 'destroy'])->name('pr.attachment.destroy');
});
