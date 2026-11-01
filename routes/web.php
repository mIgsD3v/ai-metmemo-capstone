<?php

use App\Http\Controllers\Admin;
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
Route::pattern('user', $uuid);
Route::pattern('role', $uuid);
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
    Route::get('/insights', InsightsController::class)->middleware('permission:insights.view')->name('insights');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');

    // Each feature is behind a permission (Roles page). Every account starts as a Member, which has all of them.
    Route::resource('cards', CardController::class)->except('show')
        ->middlewareFor('index', 'permission:cards.view')
        ->middlewareFor(['create', 'store'], 'permission:cards.create')
        ->middlewareFor(['edit', 'update'], 'permission:cards.update')
        ->middlewareFor('destroy', 'permission:cards.delete');
    Route::get('/cards/{card}/print', [CardController::class, 'print'])->middleware('permission:cards.view')->name('cards.print');
    Route::get('/cards/{card}/vcard', [CardController::class, 'vcard'])->middleware('permission:cards.view')->name('cards.vcard');
    Route::patch('/cards/{card}/visibility', [CardController::class, 'visibility'])->middleware('permission:cards.update')->name('cards.visibility');
    // Before the resource, so "export" isn't read as a contact id.
    Route::get('/contacts/export/{format}', ContactExportController::class)->whereIn('format', ['csv', 'xlsx', 'vcf'])->middleware('permission:contacts.export')->name('contacts.export');
    Route::resource('contacts', ContactController::class)
        ->middlewareFor(['index', 'show'], 'permission:contacts.view')
        ->middlewareFor(['create', 'store'], 'permission:contacts.create')
        ->middlewareFor(['edit', 'update'], 'permission:contacts.update')
        ->middlewareFor('destroy', 'permission:contacts.delete');
    Route::post('/contacts/{contact}/followed-up', [ContactController::class, 'followedUp'])->middleware('permission:contacts.update')->name('contacts.followed-up');
    Route::post('/contacts/{contact}/notes', [ContactNoteController::class, 'store'])->middleware('permission:contacts.update')->name('contacts.notes.store');

    // Follow-up assistant: asks the AI about your own contacts and notes; drafts only, never sends.
    Route::middleware('permission:assistant.use')->group(function () {
        Route::get('/assistant', [AssistantController::class, 'show'])->name('assistant.show');
        Route::post('/assistant/ask', [AssistantController::class, 'ask'])->middleware('throttle:20,1')->name('assistant.ask');
        Route::get('/assistant/{conversation}', [AssistantController::class, 'show'])->name('assistant.conversation');
        Route::delete('/assistant/{conversation}', [AssistantController::class, 'destroy'])->name('assistant.destroy');
    });

    // Scan a paper business card: photo -> AI reads fields -> owner reviews -> saved as a contact.
    Route::middleware('permission:scan.use')->group(function () {
        Route::get('/scan', [ScanController::class, 'create'])->name('scan.create');
        Route::post('/scan/read', [ScanController::class, 'read'])->middleware('throttle:10,1')->name('scan.read');
        Route::post('/scan', [ScanController::class, 'store'])->name('scan.store');
    });

    // Administration: app-wide counts, accounts and roles. Never anyone's contacts, notes or chats.
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', Admin\OverviewController::class)->middleware('permission:overview.view')->name('overview');

        Route::get('/users', [Admin\UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
        Route::patch('/users/{user}/role', [Admin\UserController::class, 'updateRole'])->middleware('permission:users.update')->name('users.role');
        Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');

        Route::get('/roles', [Admin\RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
        Route::post('/roles', [Admin\RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
        Route::patch('/roles/{role}', [Admin\RoleController::class, 'update'])->middleware('permission:roles.update')->name('roles.update');
        Route::put('/roles/{role}/permissions', [Admin\RoleController::class, 'permission'])->middleware('permission:roles.update')->name('roles.permission');
        Route::delete('/roles/{role}', [Admin\RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');
    });
});
