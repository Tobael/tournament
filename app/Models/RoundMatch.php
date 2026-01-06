<?php

namespace App\Models;

use App\Enums\RoundMatchResult;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundMatch extends Model
{
    protected $fillable = [
        'result',
        'round_id',
        'player_a_id',
        'player_b_id',
    ];

    protected function casts(): array
    {
        return [
            'result' => RoundMatchResult::class,
        ];
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
