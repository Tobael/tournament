<?php

namespace App\Models;

use App\Enums\RoundMatchResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundMatch extends Model
{
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
}
