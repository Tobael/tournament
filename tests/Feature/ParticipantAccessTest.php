<?php

use App\Enums\RoundMatchResult;
use App\Enums\Status;
use App\Livewire\Tournament as TournamentPage;
use App\Livewire\Tournaments;
use App\Models\Group;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function eventForParticipants(): Tournament
{
    $group = Group::create(['name' => 'Friday Magic']);

    return Tournament::create(['name' => 'Friday Night', 'status' => Status::OPEN, 'group_id' => $group->id]);
}

test('event links allow viewing and joining without an account login', function () {
    $event = eventForParticipants();
    $this->get(route('play', $event->invite_token))->assertOk()->assertSee('Ohne Passwort');
    $this->post(route('play.join', $event->invite_token), ['name' => 'Alex', 'deckname' => 'Goblins'])->assertRedirect(route('play', $event->invite_token));
    $participant = $event->users()->first();
    expect($participant->user->is_admin)->toBeFalsy();
    expect(auth()->check())->toBeFalse();
    expect(session('participants.'.$event->id.'.id'))->toBe($participant->id);
    expect($participant->recovery_hash)->toBe(hash('sha256', session('participants.'.$event->id.'.token')));
    $this->get(route('play', $event->invite_token))->assertOk()->assertSee('Du bist dabei.');
});

test('private recovery links restore only the matching event identity', function () {
    $event = eventForParticipants();
    $this->post(route('play.join', $event->invite_token), ['name' => 'Alex']);
    $token = session('participants.'.$event->id.'.token');
    $id = session('participants.'.$event->id.'.id');
    session()->flush();
    $this->post(route('play.recover'), ['token' => str_repeat('a', 64)])->assertSessionHasErrors('token');
    $this->post(route('play.recover'), ['token' => $token])->assertRedirect(route('play', $event->invite_token));
    expect(session('participants.'.$event->id.'.id'))->toBe($id);
    expect(auth()->check())->toBeFalse();
});

test('joining is idempotent and does not replace a private recovery token', function () {
    $event = eventForParticipants();
    $this->post(route('play.join', $event->invite_token), ['name' => 'Alex']);
    $token = session('participants.'.$event->id.'.token');
    $this->post(route('play.join', $event->invite_token), ['name' => 'Different name']);
    expect($event->users()->count())->toBe(1);
    expect(session('participants.'.$event->id.'.token'))->toBe($token);
});

test('names cannot reclaim another participant and closed events reject joining', function () {
    $event = eventForParticipants();
    $this->post(route('play.join', $event->invite_token), ['name' => 'Alex']);
    session()->flush();
    $this->post(route('play.join', $event->invite_token), ['name' => 'alex'])->assertSessionHasErrors('name');
    $event->update(['status' => Status::CLOSED]);
    $this->post(route('play.join', $event->invite_token), ['name' => 'Sam'])->assertUnprocessable();
});

test('participants cannot invoke organizer actions directly', function () {
    $event = eventForParticipants();
    foreach (['startNextRound', 'finishTournament'] as $action) {
        Livewire::test(TournamentPage::class, ['tournament' => $event])->call($action)->assertForbidden();
    }
    $this->actingAs(User::factory()->create());
    foreach (['createTournament', 'editTournament', 'deleteTournament'] as $action) {
        Livewire::test(Tournaments::class)->call($action)->assertForbidden();
    }
});

test('only players can report their match and the first report is final', function () {
    $event = eventForParticipants();
    $players = collect(['Alex', 'Sam'])->map(function ($name) use ($event) {
        return $event->users()->create(['user_id' => User::factory()->create(['name' => $name])->id]);
    });
    $event->update(['status' => Status::IN_PROGRESS]);
    $round = $event->rounds()->create(['round' => 1]);
    $match = $round->matches()->create(['player_a_id' => $players[0]->id, 'player_b_id' => $players[1]->id]);
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('updateCurrentMatchForUser', RoundMatchResult::TWOZERO->value)->assertForbidden();
    $players[0]->forceFill(['recovery_hash' => hash('sha256', 'private-test-token')])->save();
    $this->withSession(['participants.'.$event->id => ['id' => $players[0]->id, 'token' => 'private-test-token']]);
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('updateCurrentMatchForUser', RoundMatchResult::TWOZERO->value)->assertOk();
    expect($match->refresh()->result)->toBe(RoundMatchResult::TWOZERO);
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('updateCurrentMatchForUser', RoundMatchResult::ZEROTWO->value)->assertStatus(409);
});

test('nonparticipants and bye players render safely', function () {
    $event = eventForParticipants();
    $player = $event->users()->create(['user_id' => User::factory()->create()->id]);
    $round = $event->rounds()->create(['round' => 1]);
    $round->matches()->create(['player_a_id' => $player->id, 'result' => RoundMatchResult::TWOZERO]);
    $event->update(['status' => Status::IN_PROGRESS]);
    $this->get(route('play', $event->invite_token))->assertOk();
    $player->forceFill(['recovery_hash' => hash('sha256', 'private-test-token')])->save();
    $this->withSession(['participants.'.$event->id => ['id' => $player->id, 'token' => 'private-test-token']])->get(route('play', $event->invite_token))->assertOk()->assertSee('Freilos');
});

test('organizers can replace lost links and revoke the previous identity', function () {
    $event = eventForParticipants();
    $this->post(route('play.join', $event->invite_token), ['name' => 'Alex']);
    $identity = session('participants.'.$event->id);
    $participant = $event->users()->first();
    $this->actingAs(User::factory()->create())->post(route('participant.recovery', [$event, $participant]))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->post(route('participant.recovery', [$event, $participant]))->assertRedirect();
    $newLink = session('replacement_links.'.$event->id.'.url');
    auth()->logout();
    $this->withSession(['participants.'.$event->id => $identity]);
    expect($event->participant())->toBeNull();
    $this->post(route('play.recover'), ['token' => $identity['token']])->assertSessionHasErrors('token');
    $this->post(route('play.recover'), ['token' => explode('#', $newLink)[1]])->assertRedirect(route('play', $event->invite_token));
});

test('organizer screens render and creating tournaments preserves the selected group', function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->get(route('tournaments'))->assertOk()->assertSee('Zusammen an den Tisch.');
    $group = Group::first();
    Livewire::test(Tournaments::class)->set('name', 'Friday Night')->call('createTournament')->assertHasNoErrors()->set('name', 'Saturday')->call('createTournament')->assertHasNoErrors();
    expect(Tournament::count())->toBe(2);
    expect(Tournament::first()->group_id)->toBe($group->id);
    $this->get(route('tournament', Tournament::first()))->assertOk()->assertSee('Diesen Link');
    $this->get(route('play.return'))->assertOk();
});

test('a revoked device can join under a new name and receives a usable identity', function () {
    $event = eventForParticipants();
    $this->post(route('play.join', $event->invite_token), ['name' => 'Alex']);
    $event->users()->first()->forceFill(['recovery_hash' => hash('sha256', 'replaced')])->save();
    $this->post(route('play.join', $event->invite_token), ['name' => 'Another Alex'])->assertRedirect();
    expect($event->participant()?->user->name)->toBe('Another Alex');
});

test('tournament search and status filters narrow the organizer list', function () {
    $event = eventForParticipants();
    Tournament::create(['name' => 'Saturday', 'status' => Status::CLOSED, 'group_id' => $event->group_id]);
    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(Tournaments::class)->set('search', 'Friday')->assertSee('Friday Night')->assertDontSee('Saturday')
        ->set('search', '')->set('status', 'CLOSED')->assertSee('Saturday')->assertDontSee('Friday Night');
});

test('standings share places for ties without queries per participant', function () {
    $event = eventForParticipants();
    foreach (range(1, 12) as $number) {
        $event->users()->create(['user_id' => User::factory()->create()->id]);
    }
    $fresh = Tournament::find($event->id);
    DB::enableQueryLog();
    $standings = $fresh->standings();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($standings)->toHaveCount(12);
    expect($standings->pluck('place')->unique()->all())->toBe([1]);
    expect(count($queries))->toBeLessThanOrEqual(3);
});

test('result controls reverse pairing order for the second player', function () {
    $event = eventForParticipants();
    $a = $event->users()->create(['user_id' => User::factory()->create(['name' => 'Opponent'])->id]);
    $b = $event->users()->create(['user_id' => User::factory()->create(['name' => 'Me'])->id]);
    $b->forceFill(['recovery_hash' => hash('sha256', 'private-test-token')])->save();
    $round = $event->rounds()->create(['round' => 1]);
    $match = $round->matches()->create(['player_a_id' => $a->id, 'player_b_id' => $b->id]);
    $event->update(['status' => Status::IN_PROGRESS]);
    $this->withSession(['participants.'.$event->id => ['id' => $b->id, 'token' => 'private-test-token']])
        ->get(route('play', $event->invite_token))->assertOk()->assertSee('Dein Sieg')->assertSee("updateCurrentMatchForUser('0-2')", false);
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('updateCurrentMatchForUser', RoundMatchResult::ZEROTWO->value)->assertOk();
    expect($match->refresh()->result)->toBe(RoundMatchResult::ZEROTWO);
    expect($b->fresh()->points())->toBe(2);
});

test('the landing page opens an invitation without accepting arbitrary redirects', function () {
    $event = eventForParticipants();
    $this->get('/')->assertOk()->assertSee('Ohne Anmeldung')->assertDontSee('login-button');
    $this->get(route('play.open', ['invite' => route('play', $event->invite_token)]))->assertRedirect(route('play', $event->invite_token));
    $this->get(route('play.open', ['invite' => $event->invite_token]))->assertRedirect(route('play', $event->invite_token));
    $this->get(route('play.open', ['invite' => 'https://example.com/elsewhere']))->assertSessionHasErrors('invite');
});

test('an organizer can join, manage and play the same tournament without changing accounts', function () {
    $event = eventForParticipants();
    $organizer = User::factory()->admin()->create(['name' => 'Alex']);
    $opponent = User::factory()->create(['name' => 'Sam']);
    $event->users()->create(['user_id' => $opponent->id]);
    $this->actingAs($organizer)->get(route('tournament', $event))->assertOk()->assertSee('Organisieren und mitspielen.');
    $this->post(route('play.join', $event->invite_token), ['deckname' => 'Goblins'])->assertRedirect();
    expect(User::count())->toBe(2);
    expect($event->participant()->user_id)->toBe($organizer->id);
    $token = session('participants.'.$event->id.'.token');
    $this->post(route('play.join', $event->invite_token))->assertRedirect();
    expect(session('participants.'.$event->id.'.token'))->toBe($token);
    expect($event->users()->count())->toBe(2);
    $this->get(route('tournament', $event))->assertOk()->assertSee('Mein Spiel')->assertSee('Organisation');
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('startNextRound')->assertOk();
    $match = $event->fresh()->currentRound()->matches()->first();
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('updateCurrentMatchForUser', RoundMatchResult::TWOONE->value)->assertOk();
    expect($match->refresh()->result)->toBe(RoundMatchResult::TWOONE);
    $this->assertAuthenticatedAs($organizer);
});

test('an existing guest participation survives organizer login and recovery', function () {
    $event = eventForParticipants();
    $this->post(route('play.join', $event->invite_token), ['name' => 'Player Alex']);
    $identity = session('participants.'.$event->id);
    $organizer = User::factory()->admin()->create();
    $this->actingAs($organizer);
    expect($event->participant()->id)->toBe($identity['id']);
    $this->post(route('play.join', $event->invite_token))->assertRedirect();
    expect($event->users()->count())->toBe(1);
    session()->forget('participants');
    $this->postJson(route('play.recover'), ['token' => $identity['token']])->assertOk()->assertJson(['url' => route('play', $event->invite_token)]);
    expect($event->participant()->id)->toBe($identity['id']);
    $this->assertAuthenticatedAs($organizer);
    $this->get(route('play', $event->invite_token))->assertOk()->assertSee('Player Alex')->assertSee('Organisation');
});

test('recovering an organizer participation does not grant organizer permissions', function () {
    $event = eventForParticipants();
    $organizer = User::factory()->admin()->create();
    $this->actingAs($organizer)->post(route('play.join', $event->invite_token))->assertRedirect();
    $token = session('participants.'.$event->id.'.token');
    auth()->logout();
    session()->flush();
    $this->postJson(route('play.recover'), ['token' => $token])->assertOk();
    $this->assertGuest();
    expect($event->participant()->user_id)->toBe($organizer->id);
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('startNextRound')->assertForbidden();
});

test('automatic device recovery rejects expired tokens with a JSON validation error', function () {
    $this->postJson(route('play.recover'), ['token' => str_repeat('x', 64)])->assertUnprocessable()->assertJsonValidationErrors('token');
    $this->assertGuest();
});

test('replacement links remain visible across polling and are scoped to their tournament', function () {
    $event = eventForParticipants();
    $participant = $event->users()->create(['user_id' => User::factory()->create()->id]);
    $this->actingAs(User::factory()->admin()->create());
    $this->post(route('participant.recovery', [$event, $participant]))->assertRedirect();
    $link = session('replacement_links.'.$event->id.'.url');
    $this->get(route('tournament', $event))->assertOk()->assertSee($link);
    $this->get(route('tournament', $event))->assertOk()->assertSee($link);
    $other = Tournament::create(['name' => 'Other event', 'status' => Status::OPEN, 'group_id' => $event->group_id]);
    $this->get(route('tournament', $other))->assertOk()->assertDontSee($link);
});
