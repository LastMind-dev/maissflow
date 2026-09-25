<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('app') : redirect()->route('login'));

Route::view('/politica-de-privacidade', 'privacy-policy')->name('privacy-policy');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('api/app')->group(function (): void {
    Route::get('/bootstrap', [AppController::class, 'bootstrap']);

    Route::get('/automations', [AutomationController::class, 'index']);
    Route::post('/automations', [AutomationController::class, 'store']);
    Route::get('/automations/{automation}', [AutomationController::class, 'show']);
    Route::put('/automations/{automation}', [AutomationController::class, 'update']);
    Route::put('/automations/{automation}/graph', [AutomationController::class, 'saveGraph']);
    Route::post('/automations/{automation}/publish', [AutomationController::class, 'publish']);
    Route::post('/automations/{automation}/duplicate', [AutomationController::class, 'duplicate']);
    Route::post('/automations/{automation}/simulate', [AutomationController::class, 'simulate']);

    Route::get('/inbox', [InboxController::class, 'index']);
    Route::get('/inbox/{conversation}', [InboxController::class, 'show']);
    Route::post('/inbox/{conversation}/reply', [InboxController::class, 'reply']);
    Route::post('/inbox/{conversation}/take', [InboxController::class, 'take']);
    Route::put('/inbox/{conversation}/mode', [InboxController::class, 'mode']);

    Route::get('/contacts', [ContactController::class, 'index']);
    Route::get('/contacts/export', [ContactController::class, 'export']);

    Route::get('/team', [TeamController::class, 'index']);
    Route::post('/team', [TeamController::class, 'store']);
    Route::put('/team/{teamMember}', [TeamController::class, 'update']);
    Route::get('/operations', [OperationsController::class, 'index']);

    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:5,1');

    Route::put('/channel', [ChannelController::class, 'update']);
    Route::post('/channel/test', [ChannelController::class, 'test']);
});

Route::get('/app/{path?}', [AppController::class, 'show'])
    ->middleware('auth')
    ->where('path', '.*')
    ->name('app');
