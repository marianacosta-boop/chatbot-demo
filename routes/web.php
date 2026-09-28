<?php

use App\Http\Controllers\ChatController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/chat-demo'));

// LOCAL TEST ONLY — logs in the seeded demo client without a login form. Delete before deploying.
if (app()->environment('local')) {
    Route::get('/chat-demo', function () {
        Auth::login(User::where('email', 'mariana.costa1@acin.com')->firstOrFail());
        return view('chat-demo');
    });
}

Route::middleware(['auth'])->prefix('chat')->group(function () {
    Route::post('/conversations', [ChatController::class, 'start']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send']);
});
