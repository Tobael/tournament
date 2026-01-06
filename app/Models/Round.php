<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    protected $fillable = [
        'round',
        'tournament_id',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(RoundMatch::class);
    }

    public function isCompleted(): bool {
        return $this->matches->reduce(fn(bool $carry, RoundMatch $match) => $carry && $match->result, false);
    }
}
