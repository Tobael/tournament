<?php

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Models\Tournament;

class TournamentTvController extends Controller
{
    private function latestTournament(): ?Tournament
    {
        return Tournament::forTv();
    }

    public function latest()
    {
        $tournament = $this->latestTournament();

        return response()->view('tournament-tv', [
            ...($tournament ? $this->board($tournament) : ['tournament' => null]),
            'automatic' => true,
        ])->header('Cache-Control', 'no-store');
    }

    public function latestState()
    {
        return $this->stateResponse($this->latestTournament());
    }

    private function board(Tournament $tournament): array
    {
        $tournament->load(['group', 'users.user', 'matches', 'rounds.matches.playerA.user', 'rounds.matches.playerB.user', 'rounds.matches.players.user']);

        return [
            'tournament' => $tournament,
            'standings' => $tournament->standings(),
            'currentRound' => $tournament->currentRound(),
        ];
    }

    public function show(Tournament $tournament)
    {
        return response()->view('tournament-tv', $this->board($tournament))->header('Cache-Control', 'no-store');
    }

    public function state(Tournament $tournament)
    {
        return $this->stateResponse($tournament);
    }

    private function stateResponse(?Tournament $tournament)
    {
        return response()->json([
            'html' => $tournament ? view('partials.tv-board', $this->board($tournament))->render() : view('partials.tv-empty')->render(),
            'closed' => $tournament?->status === Status::CLOSED,
            'title' => ($tournament?->name ?? 'Spielrunde').' · TV · Spielrunde',
            'playUrl' => $tournament ? route('play', $tournament->invite_token) : route('home', ['home' => 1]),
            'music' => ['url' => $tournament?->tv_youtube_url, 'revision' => $tournament?->tv_music_revision],
            'serverTime' => now()->getTimestampMs(),
        ])->header('Cache-Control', 'no-store');
    }
}
