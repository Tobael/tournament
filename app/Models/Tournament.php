<?php

namespace App\Models;

use App\Enums\Status;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Tournament extends Model
{
    /** @use HasFactory<\Database\Factories\TournamentFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'group_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(TournamentUser::class);
    }

    public function participants(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, TournamentUser::class, 'tournament_id', 'id', 'id', 'user_id');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }

    public function currentRound(): Round {
        return $this->rounds()->orderBy('round', 'desc')->first();
    }

    public function matches(): HasManyThrough
    {
        return $this->hasManyThrough(RoundMatch::class, Round::class);
    }
}
