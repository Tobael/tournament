<?php

use App\Models\Tournament;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tournament.{id}', function ($user, $id) {
    return Tournament::find($id)->users()->where('id', $user->id)->exists();
});
