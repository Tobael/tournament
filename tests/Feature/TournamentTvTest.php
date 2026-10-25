<?php

use App\Enums\RoundMatchResult;
use App\Enums\Status;
use App\Models\Group;
use App\Models\Tournament;
use App\Models\User;

function tvEvent(string $format = 'sealed'): array
{
    $event = Tournament::create(['name' => 'TV Night', 'format' => $format, 'group_id' => Group::firstOrCreate(['name' => 'Friends'])->id, 'status' => Status::OPEN]);
    $players = collect(['Alex', 'Sam', 'Chris'])->map(fn ($name) => $event->users()->create(['user_id' => User::factory()->create(['name' => $name])->id]));

    return [$event, $players];
}

test('the TV page is public through the invitation and contains no participation or organizer controls', function () {
    [$event] = tvEvent();
    $this->get(route('play', $event->invite_token))->assertSee(route('tv'), false);
    $this->get(route('play.tv', $event->invite_token))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('Anmeldung läuft')->assertSee('Alex')->assertSee('data-tv-music-form', false)
        ->assertSee('strict-origin-when-cross-origin')->assertDontSee('wire:click')->assertDontSee('data-device-participant');
    $this->get('/play/not-a-token/tv')->assertNotFound();
    $this->get('/play/not-a-token/tv/state')->assertNotFound();
    $this->post(route('play.tv.state', $event->invite_token))->assertStatus(405);
});

test('TV refreshes reveal new rounds, scores, shared winners and final state without private tokens', function () {
    [$event, $players] = tvEvent();
    $players[0]->forceFill(['recovery_hash' => hash('sha256', 'private-return-token')])->save();
    $event->update(['status' => Status::IN_PROGRESS]);
    $round = $event->rounds()->create(['round' => 1]);
    $match = $round->matches()->create(['player_a_id' => $players[0]->id, 'player_b_id' => $players[1]->id, 'timer_ends_at' => now()->addMinutes(50)]);
    $bye = $round->matches()->create(['player_a_id' => $players[2]->id, 'result' => RoundMatchResult::TWOZERO]);
    $state = $this->getJson(route('play.tv.state', $event->invite_token))->assertOk()->assertJsonPath('closed', false)->json();
    expect($state['html'])->toContain('Runde 1', 'data-tv-deadline', 'Freilos')->not->toContain('recovery_hash', hash('sha256', 'private-return-token'));
    expect($state['serverTime'])->toBeInt();
    $match->update(['result' => RoundMatchResult::TWOZERO]);
    $event->update(['status' => Status::CLOSED]);
    $html = $this->getJson(route('play.tv.state', $event->invite_token))->assertJsonPath('closed', true)->json('html');
    expect($html)->toContain('Endstand', 'Geteilter Turniersieg', 'Alex &amp; Chris')->not->toContain('data-tv-deadline');
});

test('commander TV tables show every seat and the recorded points and escape player names', function () {
    [$event, $players] = tvEvent('commander');
    $players[0]->user->update(['name' => '<script>alert(1)</script>']);
    $event->update(['status' => Status::IN_PROGRESS]);
    $round = $event->rounds()->create(['round' => 1]);
    $match = $round->matches()->create(['player_a_id' => $players[0]->id, 'player_b_id' => $players[1]->id]);
    $match->players()->attach($players->pluck('id')->all());
    $match->update(['commander_scores' => [$players[0]->id => 0, $players[1]->id => 3, $players[2]->id => 0]]);
    $html = $this->getJson(route('play.tv.state', $event->invite_token))->assertOk()->json('html');
    expect($html)->toContain('COMMANDER', 'Sam', 'Chris', '3 P', '&lt;script&gt;')->not->toContain('<script>');
});

test('the permanent TV address waits for the first tournament and links from the homepage', function () {
    $this->get(route('tv'))->assertOk()->assertSee('Noch kein Turnier vorhanden')->assertSee('data-automatic="1"', false);
    $this->getJson(route('tv.state'))->assertOk()->assertJsonPath('closed', false)->assertJsonPath('playUrl', route('home', ['home' => 1]));
    $this->get(route('home'))->assertSee(route('tv'), false);
    [$event] = tvEvent();
    $this->getJson(route('tv.state'))->assertJsonPath('playUrl', route('play', $event->invite_token));
});

test('automatic TV selects the newest running event before a newer non-running event', function () {
    [$old] = tvEvent();
    $old->update(['name' => 'Older running', 'status' => Status::IN_PROGRESS, 'created_at' => now()->subDays(3)]);
    [$running] = tvEvent();
    $running->update(['name' => 'Newest running', 'status' => Status::IN_PROGRESS, 'created_at' => now()->subDays(2)]);
    [$latest] = tvEvent();
    $latest->update(['name' => 'Latest event', 'created_at' => now()->subDay()]);
    $this->get(route('tv'))->assertOk()->assertSee('Newest running')->assertDontSee('Older running')->assertDontSee('Latest event');
    $this->getJson(route('tv.state'))->assertJsonPath('playUrl', route('play', $running->invite_token));
    $running->update(['status' => Status::CLOSED]);
    $this->getJson(route('tv.state'))->assertJsonPath('playUrl', route('play', $old->invite_token));
    $old->update(['status' => Status::CLOSED]);
    $this->getJson(route('tv.state'))->assertJsonPath('playUrl', route('play', $latest->invite_token))->assertJsonPath('closed', false);
    $latest->update(['status' => Status::CLOSED]);
    $this->get(route('tv'))->assertOk()->assertSee('Latest event')->assertSee('data-automatic="1"', false);
    $this->getJson(route('tv.state'))->assertJsonPath('closed', true);
    [$next] = tvEvent();
    $next->update(['name' => 'New evening', 'status' => Status::IN_PROGRESS]);
    $this->getJson(route('tv.state'))->assertJsonPath('closed', false)->assertJsonPath('title', 'New evening · TV · Spielrunde');
    // The old invitation-specific TV URL remains pinned to that event.
    $this->get(route('play.tv', $old->invite_token))->assertSee('Older running')->assertDontSee('New evening');
});

test('automatic TV breaks matching creation timestamps by newest ID and ignores scheduled dates', function () {
    [$first] = tvEvent();
    [$last] = tvEvent();
    $first->update(['name' => 'Future scheduled event', 'created_at' => now(), 'scheduled_on' => now()->addMonth()->format('Y-m-d')]);
    $last->update(['name' => 'Newest created event', 'created_at' => $first->created_at, 'scheduled_on' => now()->subMonth()->format('Y-m-d')]);
    $this->getJson(route('tv.state'))->assertJsonPath('playUrl', route('play', $last->invite_token));
});
