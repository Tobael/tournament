<?php

namespace App\Models;

use App\Enums\Status;
use Database\Factories\TournamentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Tournament extends Model
{
    /** @use HasFactory<TournamentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(fn ($tournament) => $tournament->invite_token = (string) Str::uuid());
    }

    public function participant(): ?TournamentUser
    {
        if (auth()->check()) {
            return $this->users()->where('user_id', auth()->id())->first();
        }

        $identity = session('participants.'.$this->id);
        if (! $identity || empty($identity['token'])) {
            return null;
        }

        return $this->users()->whereKey($identity['id'])->where('recovery_hash', hash('sha256', $identity['token']))->first();
    }

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
        return $this->hasMany(TournamentUser::class)->chaperone();
    }

    public function participants(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, TournamentUser::class, 'tournament_id', 'id', 'id', 'user_id');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->chaperone();
    }

    public function currentRound(): ?Round
    {
        return $this->relationLoaded('rounds') ? $this->rounds->sortByDesc('round')->first() : $this->rounds()->orderBy('round', 'desc')->first();
    }

    public function matches(): HasManyThrough
    {
        return $this->hasManyThrough(RoundMatch::class, Round::class);
    }

    public function standings(): Collection
    {
        $this->loadMissing(['users.user', 'matches']);
        $currentPoints = null;
        $place = 0;

        return $this->users
            ->map(fn (TournamentUser $tuser) => [
                'id' => $tuser->id,
                'name' => $tuser->user->name,
                'deck' => $tuser->deckname,
                'games' => $tuser->games(),
                'points' => $tuser->points(),
            ])
            ->sortByDesc('points')
            ->values()
            ->map(function (array $standing) use (&$currentPoints, &$place) {
                if ($currentPoints !== $standing['points']) {
                    $currentPoints = $standing['points'];
                    $place++;
                }

                $standing['place'] = $place;

                return $standing;
            });
    }

    public function allMatchesGenerated(): bool
    {
        $participantCount = $this->users->count();

        if ($participantCount % 2 != 0) {
            // we need to account for bye games (bye counts as an additional player)
            $participantCount++;
        }

        return $this->status == Status::IN_PROGRESS && $this->matches->count() >= ($participantCount * ($participantCount - 1)) / 2;
    }
}
