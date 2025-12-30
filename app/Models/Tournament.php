<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(TournamentUser::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }
}
