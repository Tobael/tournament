<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentUser extends Model
{
    /** @use HasFactory<\Database\Factories\TournamentUserFactory> */
    use HasFactory;

    protected $fillable = [
        'deck_name',
        'tournament_id',
        'user_id',
        'deckname',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
