<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactExportController;
use App\Http\Controllers\ContactNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\InsightsController;
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
Route::pattern('conversation', $uuid);
Route::get('/c/{card}', [PublicCardController::class, 'show'])->name('public-card.show');
Route::get('/c/{card}/vcard', [PublicCardController::class, 'vcard'])->name('public-card.vcard');
Route::post('/c/{card}/leads', [PublicCardController::class, 'storeLead'])
    ->middleware('throttle:5,1')
    ->name('public-card.leads.store');
// Two MetMemo users swap cards: both become each other's contacts. The GET only sends guests to log in first.
Route::middleware('auth')->group(function () {
    Route::get('/c/{card}/swap', [PublicCardController::class, 'startSwap'])->name('public-card.swap.start');
    Route::post('/c/{card}/swap', [PublicCardController::class, 'swap'])->middleware('throttle:10,1')->name('public-card.swap');
});

// "Continue with Google" on the login and register pages (only when GOOGLE_CLIENT_ID is set).
Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/insights', InsightsController::class)->name('insights');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');

    Route::resource('cards', CardController::class)->except('show');
    Route::get('/cards/{card}/print', [CardController::class, 'print'])->name('cards.print');
    Route::get('/cards/{card}/vcard', [CardController::class, 'vcard'])->name('cards.vcard');
    Route::patch('/cards/{card}/visibility', [CardController::class, 'visibility'])->name('cards.visibility');
    // Before the resource, so "export" isn't read as a contact id.
    Route::get('/contacts/export/{format}', ContactExportController::class)->whereIn('format', ['csv', 'xlsx', 'vcf'])->name('contacts.export');
    Route::resource('contacts', ContactController::class);
    Route::post('/contacts/{contact}/followed-up', [ContactController::class, 'followedUp'])->name('contacts.followed-up');
    Route::post('/contacts/{contact}/notes', [ContactNoteController::class, 'store'])->name('contacts.notes.store');

    // Follow-up assistant: asks the AI about your own contacts and notes; drafts only, never sends.
    Route::get('/assistant', [AssistantController::class, 'show'])->name('assistant.show');
    Route::post('/assistant/ask', [AssistantController::class, 'ask'])->middleware('throttle:20,1')->name('assistant.ask');
    Route::get('/assistant/{conversation}', [AssistantController::class, 'show'])->name('assistant.conversation');
    Route::delete('/assistant/{conversation}', [AssistantController::class, 'destroy'])->name('assistant.destroy');

    // Scan a paper business card: photo -> AI reads fields -> owner reviews -> saved as a contact.
    Route::get('/scan', [ScanController::class, 'create'])->name('scan.create');
    Route::post('/scan/read', [ScanController::class, 'read'])->middleware('throttle:10,1')->name('scan.read');
    Route::post('/scan', [ScanController::class, 'store'])->name('scan.store');
});
