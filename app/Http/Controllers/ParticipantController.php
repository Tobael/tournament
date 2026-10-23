<?php

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Models\Tournament;
use App\Models\TournamentUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ParticipantController extends Controller
{
    public function open(Request $request)
    {
        $data = $request->validate(['invite' => ['required', 'string', 'max:500']]);
        $token = basename(rtrim(parse_url(trim($data['invite']), PHP_URL_PATH) ?: '', '/'));
        $tournament = Str::isUuid($token) ? Tournament::where('invite_token', $token)->first() : null;
        if (! $tournament) {
            throw ValidationException::withMessages(['invite' => 'Dieser Event-Link wurde nicht gefunden. Bitte prüfe den Link deiner Runde.']);
        }

        return redirect()->route('play', $tournament->invite_token);
    }

    public function join(Request $request, Tournament $tournament)
    {
        abort_if(auth()->check(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'deckname' => ['nullable', 'string', 'max:255'],
        ]);
        $token = Str::random(64);
        $participant = DB::transaction(function () use ($tournament, $data, $token) {
            $tournament->refresh();
            abort_unless($tournament->status === Status::OPEN, 422);
            if ($existing = $tournament->participant()) {
                return $existing;
            }
            if ($tournament->participants()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))])->exists()) {
                throw ValidationException::withMessages(['name' => 'Dieser Name ist schon dabei. Nutze deinen Rückkehr-Link oder ergänze deinen Namen.']);
            }
            $user = User::create([
                'name' => trim($data['name']),
                'email' => Str::uuid().'@participant.invalid',
                'password' => Str::random(64),
                'is_admin' => false,
            ]);
            $participant = $tournament->users()->create(['user_id' => $user->id, 'deckname' => $data['deckname'] ?? null]);
            $participant->forceFill(['recovery_hash' => hash('sha256', $token)])->save();

            return $participant;
        });
        if (session('participants.'.$tournament->id.'.id') !== $participant->id
            || ! hash_equals($participant->recovery_hash, hash('sha256', session('participants.'.$tournament->id.'.token', '')))) {
            $request->session()->regenerate();
            session(['participants.'.$tournament->id => ['id' => $participant->id, 'token' => $token]]);
        }

        return redirect()->route('play', $tournament->invite_token);
    }

    public function replaceRecovery(Tournament $tournament, TournamentUser $participant)
    {
        abort_unless(auth()->user()?->is_admin, 403);
        abort_unless($participant->tournament_id === $tournament->id, 404);
        $token = Str::random(64);
        $participant->forceFill(['recovery_hash' => hash('sha256', $token)])->save();

        return redirect()->route('tournament', $tournament)->with('replacement_link', [
            'name' => $participant->user->name,
            'url' => route('play.return').'#'.$token,
        ]);
    }

    public function recover(Request $request)
    {
        abort_if(auth()->check(), 403);
        $data = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $participant = TournamentUser::where('recovery_hash', hash('sha256', $data['token']))->first();
        if (! $participant) {
            throw ValidationException::withMessages(['token' => 'Dieser Rückkehr-Link ist ungültig. Bitte die Organisation um einen neuen Link.']);
        }
        $request->session()->regenerate();
        session(['participants.'.$participant->tournament_id => ['id' => $participant->id, 'token' => $data['token']]]);

        return redirect()->route('play', $participant->tournament->invite_token);
    }
}
