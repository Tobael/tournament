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
        // A recovered event identity is independent of the organizer login.
        $identity = session('participants.'.$this->id);
        if (is_array($identity) && ! empty($identity['id']) && ! empty($identity['token'])) {
            $participant = $this->users()->whereKey($identity['id'])->where('recovery_hash', hash('sha256', $identity['token']))->first();
            if ($participant) {
                return $participant;
            }
        }

        return auth()->check() ? $this->users()->where('user_id', auth()->id())->first() : null;
    }

    protected $fillable = [
        'name',
        'status',
        'group_id',
        'format',
        'set_code',
        'set_name',
        'table_preference',
        'round_limit',
    ];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'round_limit' => 'integer',
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
        if ($this->format === 'commander') {
            return $this->rounds->count() >= $this->round_limit;
        }

        $participantCount = $this->users->count();

        if ($participantCount % 2 != 0) {
            // we need to account for bye games (bye counts as an additional player)
            $participantCount++;
        }

        return $this->status == Status::IN_PROGRESS && $this->matches->count() >= ($participantCount * ($participantCount - 1)) / 2;
    }
}
