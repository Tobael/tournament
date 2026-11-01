<?php

use App\Enums\Status;
use App\Models\Group;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\LocalTournamentSeeder;

test('local fixtures preserve organizer credentials, seat them and can be seeded repeatedly', function () {
    $organizer = User::factory()->admin()->withoutTwoFactor()->create();
    $password = $organizer->password;
    $this->seed(LocalTournamentSeeder::class);
    $this->seed(LocalTournamentSeeder::class);
    expect(Tournament::count())->toBe(4);
    expect(Tournament::withCount('users')->get()->pluck('users_count')->sort()->values()->all())->toBe([3, 4, 4, 5]);
    foreach (Tournament::all() as $event) {
        expect($event->status)->toBe(Status::IN_PROGRESS);
        expect($event->currentRound())->not->toBeNull();
        expect($event->users()->where('user_id', $organizer->id)->exists())->toBeTrue();
    }
    foreach (Tournament::where('format', 'commander')->get() as $running) {
        expect($running->currentRound()->matches()->count())->toBe(1);
        $participant = $running->users()->where('user_id', $organizer->id)->firstOrFail();
        expect($running->currentRound()->matches->flatMap->players->contains('id', $participant->id))->toBeTrue();
        expect($running->currentRound()->matches->first()->players)->toHaveCount($running->users()->count());
    }
    $sealed = Tournament::where('format', 'sealed')->firstOrFail();
    expect($sealed->currentRound()->matches()->count())->toBe(2);
    expect($organizer->fresh()->password)->toBe($password);
});

test('resetting local demos rebuilds only the generated fixtures and preserves other tournaments', function () {
    $organizer = User::factory()->admin()->withoutTwoFactor()->create();
    $unrelated = Tournament::factory()->for(Group::factory())->create();
    $password = $organizer->password;
    $this->seed(LocalTournamentSeeder::class);
    $oldIds = Tournament::where('name', 'like', 'Lokaler Test%')->pluck('id');
    $this->artisan('app:seed-local --reset')->assertSuccessful();
    expect(Tournament::whereIn('id', $oldIds)->exists())->toBeFalse();
    expect($unrelated->fresh())->not->toBeNull();
    expect(Tournament::count())->toBe(5);
    expect($organizer->fresh()->password)->toBe($password);
});
