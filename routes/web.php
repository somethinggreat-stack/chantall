<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

$pages = [
    'tax',
    'funding',
    'contact',
    'checkout',
    'terms',
    'privacy',
    'disclaimer',
    'service-agreement',
];

// Homepage
Route::get('/', function () {
    return response()->file(public_path('index.html'));
})->name('home');

// Redirect /index.html to /
Route::get('/index.html', function () {
    return redirect('/', 301);
});

// Clean pages only
foreach ($pages as $page) {
    Route::get('/' . $page, function () use ($page) {
        return response()->file(public_path($page . '.html'));
    })->name($page);

    // Redirect old .html URLs to clean URL
    Route::get('/' . $page . '.html', function () use ($page) {
        return redirect('/' . $page, 301);
    });
}

/*
|--------------------------------------------------------------------------
| Admin dashboard
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('admin.login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:8,1')->name('admin.login.submit');
    Route::post('logout', [AuthController::class, 'logout'])->name('admin.logout');

    Route::middleware('admin')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('all', [DashboardController::class, 'all'])->name('admin.all');
        Route::get('leads/{type}', [DashboardController::class, 'list'])->name('admin.list');
        Route::get('leads/{type}/{id}', [DashboardController::class, 'show'])->name('admin.show');
        Route::post('leads/{type}/{id}/status', [DashboardController::class, 'updateStatus'])->name('admin.status');

        Route::get('payments', [DashboardController::class, 'payments'])->name('admin.payments');
        Route::get('webhooks', [DashboardController::class, 'webhooks'])->name('admin.webhooks');
        Route::get('webhooks/{id}', [DashboardController::class, 'webhookDetail'])->name('admin.webhook');
        Route::get('declines', [DashboardController::class, 'declines'])->name('admin.declines');
    });
});

/*
|--------------------------------------------------------------------------
| Authorize.Net webhook receiver
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/authorize-net', [WebhookController::class, 'handle'])->name('webhooks.authorizenet');
Route::post('/', [WebhookController::class, 'handle']);