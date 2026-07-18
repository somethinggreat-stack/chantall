<?php

use App\Http\Controllers\IntakeController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Public form intake (no CSRF — static HTML forms post JSON here)
|--------------------------------------------------------------------------
*/
Route::post('/leads/popup', [IntakeController::class, 'popup']);
Route::post('/leads/contact', [IntakeController::class, 'contact']);
Route::post('/leads/tax', [IntakeController::class, 'tax']);
Route::post('/leads/funding', [IntakeController::class, 'funding']);
Route::post('/leads/enroll', [IntakeController::class, 'enroll']);

/*
|--------------------------------------------------------------------------
| Authorize.Net checkout (Accept.js — card data is tokenized in the browser)
|--------------------------------------------------------------------------
*/
Route::get('/checkout/config', [PaymentController::class, 'config']);
Route::post('/checkout/pay', [PaymentController::class, 'charge'])->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| Client onboarding — forwarded server-to-server to Apex Growth Solutions.
| The intake key lives only in .env; it is never exposed to the browser.
|--------------------------------------------------------------------------
*/
Route::post('/onboarding/submit', [OnboardingController::class, 'submit'])->middleware('throttle:20,1');
