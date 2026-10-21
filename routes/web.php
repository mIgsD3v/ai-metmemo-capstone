<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicCardController;
use App\Http\Controllers\ScanController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Public card pages (no login): shared by link or QR.
// Every {card} and {contact} in a URL must be a UUID; anything else is a 404 without a database query
// (PostgreSQL would otherwise error on a non-UUID value).
$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
Route::pattern('card', $uuid);
Route::pattern('contact', $uuid);
Route::get('/c/{card}', [PublicCardController::class, 'show'])->name('public-card.show');
Route::get('/c/{card}/vcard', [PublicCardController::class, 'vcard'])->name('public-card.vcard');
Route::post('/c/{card}/leads', [PublicCardController::class, 'storeLead'])
    ->middleware('throttle:5,1')
    ->name('public-card.leads.store');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');

    Route::resource('cards', CardController::class)->except('show');
    Route::get('/cards/{card}/print', [CardController::class, 'print'])->name('cards.print');
    Route::get('/cards/{card}/vcard', [CardController::class, 'vcard'])->name('cards.vcard');
    Route::patch('/cards/{card}/visibility', [CardController::class, 'visibility'])->name('cards.visibility');
    Route::resource('contacts', ContactController::class);
    Route::post('/contacts/{contact}/followed-up', [ContactController::class, 'followedUp'])->name('contacts.followed-up');
    Route::post('/contacts/{contact}/notes', [ContactNoteController::class, 'store'])->name('contacts.notes.store');

    // Scan a paper business card: photo -> AI reads fields -> owner reviews -> saved as a contact.
    Route::get('/scan', [ScanController::class, 'create'])->name('scan.create');
    Route::post('/scan/read', [ScanController::class, 'read'])->middleware('throttle:10,1')->name('scan.read');
    Route::post('/scan', [ScanController::class, 'store'])->name('scan.store');
});
