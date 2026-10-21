<?php

use App\Http\Controllers\Internal\AssistantDataController;
use Illuminate\Support\Facades\Route;

/*
| Read-only data for the AI service's assistant tools. Mounted at /internal behind
| VerifyInternalToken (see bootstrap/app.php). Scoped bindings make {contact} resolve
| only among {user}'s own contacts, so another user's contact is a 404.
*/

Route::scopeBindings()->prefix('users/{user}')->name('internal.')->group(function () {
    Route::get('followups', [AssistantDataController::class, 'followups'])->name('followups');
    Route::get('contacts', [AssistantDataController::class, 'contacts'])->name('contacts');
    Route::get('contacts/{contact}/notes', [AssistantDataController::class, 'notes'])->name('notes');
});
