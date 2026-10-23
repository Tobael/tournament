<?php

namespace App\Models;

use Database\Factories\TournamentUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentUser extends Model
{
    /** @use HasFactory<TournamentUserFactory> */
    use HasFactory;

    protected $hidden = ['recovery_hash'];

    protected $fillable = [
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

    public function points(): int
    {
        return $this->tournament
            ->matches
            ->filter(fn (RoundMatch $match) => $match->player_a_id === $this->id || $match->player_b_id === $this->id)
            ->reduce(function (int $carry, RoundMatch $match) {
                $index = $match->player_a_id == $this->id ? 0 : 1;

                return $carry + ($match->result?->toPoints() ?? [0, 0])[$index];
            }, 0);
    }

    public function games(): int
    {
        return $this->tournament
            ->matches
            ->filter(fn (RoundMatch $match) => $match->player_a_id === $this->id || $match->player_b_id === $this->id)
            ->filter(fn (RoundMatch $match) => $match->result)
            ->count();
    }
}
