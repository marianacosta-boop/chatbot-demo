<?php

use App\Http\Controllers\ChatController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/chat-demo'));

Route::view('/chat-demo', 'chat-demo');

Route::middleware('throttle:20,1')->prefix('chat/guest')->group(function () {
    Route::post('/conversations', [ChatController::class, 'startGuest']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'sendGuest']);
});


if (app()->environment('local')) {
    Route::get('/dev/guest', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/chat-demo');
    })->name('dev.guest');

    Route::get('/dev/login', function (Request $request) {
        Auth::login(User::where('email', 'mariana.costa1@acin.com')->firstOrFail());
        $request->session()->regenerate();

        return redirect('/chat-demo');
    })->name('dev.login');
}

Route::middleware(['auth'])->prefix('chat')->group(function () {
    Route::post('/conversations', [ChatController::class, 'start']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send']);
});
