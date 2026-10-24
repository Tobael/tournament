<?php

use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\SetVisualController;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use App\Livewire\Tournament;
use App\Livewire\Tournaments;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::view('/', 'welcome')->name('home');
Route::get('/set-visuals/{code}/artwork', [SetVisualController::class, 'artwork'])->where('code', '[a-z0-9]{2,10}')->middleware('throttle:120,1')->name('set.artwork');
Route::get('/set-visuals/{code}/symbol', [SetVisualController::class, 'symbol'])->where('code', '[a-z0-9]{2,10}')->middleware('throttle:120,1')->name('set.symbol');
Route::get('/join', [ParticipantController::class, 'open'])->middleware('throttle:30,1')->name('play.open');

Route::get('/play/{tournament:invite_token}', Tournament::class)->name('play');
Route::post('/play/{tournament:invite_token}/join', [ParticipantController::class, 'join'])->middleware('throttle:10,1')->name('play.join');
Route::view('/return', 'participant-return')->name('play.return');
Route::post('/return', [ParticipantController::class, 'recover'])->middleware('throttle:10,1')->name('play.recover');

Route::middleware(['auth'])->group(function () {
    Route::post('tournament/{tournament}/participants/{participant}/recovery', [ParticipantController::class, 'replaceRecovery'])->name('participant.recovery');
    Route::get('tournament/{tournament}', Tournament::class)->name('tournament');
    Route::get('/tournaments', Tournaments::class)->name('tournaments');

    Route::redirect('settings', 'settings/profile');
    Route::get('settings/profile', Profile::class)->name('profile.edit');
    Route::get('settings/password', Password::class)->name('user-password.edit');

    Route::get('settings/two-factor', TwoFactor::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});
