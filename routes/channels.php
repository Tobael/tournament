<?php

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tournament.{tournament}', function (User $user, Tournament $tournament) {
    return $tournament->users()->where('user_id', $user->id)->exists();
});
