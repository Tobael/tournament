<?php

namespace App;

use App\Models\Round;
use App\Models\RoundMatch;
use App\Models\Tournament;
use App\Models\TournamentUser;

class SwissTournamentHandler
{
    private function __construct(
        public Tournament $tournament,
    ) {}

    public static function create(Tournament $tournament): self
    {
        return new self($tournament);
    }

    public function generateNextRound(): void
    {
        $users = $this->tournament->users;

        $this->getNonPlayedUsers($users->first());
    }

    private function getNonPlayedUsers(TournamentUser $user): array
    {
        $matches = $user->tournament->matches()->hasUser($user)->get();
        dd($matches);
    }
}
