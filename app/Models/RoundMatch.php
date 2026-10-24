<?php

namespace App\Models;

use App\Enums\RoundMatchResult;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RoundMatch extends Model
{
    protected $fillable = [
        'result',
        'round_id',
        'player_a_id',
        'player_b_id',
        'timer_ends_at',
        'commander_scores',
    ];

    protected function casts(): array
    {
        return [
            'result' => RoundMatchResult::class,
            'timer_ends_at' => 'immutable_datetime',
            'commander_scores' => 'array',
        ];
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(TournamentUser::class, 'round_match_players')->orderByPivot('id');
    }

    public function isCompleted(): bool
    {
        return $this->result !== null || $this->commander_scores !== null;
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function playerA(): BelongsTo
    {
        return $this->belongsTo(TournamentUser::class, foreignKey: 'player_a_id');
    }

    public function playerB(): BelongsTo
    {
        return $this->belongsTo(TournamentUser::class, foreignKey: 'player_b_id');
    }

    #[Scope]
    public function hasUser(Builder $query, TournamentUser $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->where('player_a_id', $user->id)->orWhere('player_b_id', $user->id);
        });
    }
}
