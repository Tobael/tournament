<?php

use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\LocalTournamentSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('app:organizer {email} {--name=}', function () {
    $name = $this->option('name') ?: $this->ask('Name');
    $password = $this->secret('Password (at least 12 characters)');
    $confirmation = $this->secret('Confirm password');
    $data = ['name' => $name, 'email' => $this->argument('email'), 'password' => $password, 'password_confirmation' => $confirmation];
    $validator = Validator::make($data, [
        'name' => ['required', 'string', 'max:80'],
        'email' => ['required', 'email', 'unique:users,email'],
        'password' => ['required', 'confirmed', Password::min(12)],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }
    User::create(['name' => $name, 'email' => $data['email'], 'password' => $password, 'is_admin' => true]);
    $this->info('Organizer created. Sign in and enable two-factor authentication in Settings.');

    return 0;
})->purpose('Create an organizer with an interactive password prompt');

Artisan::command('app:seed-local {--reset : Rebuild only the generated local demo tournaments}', function () {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Demo fixtures are only available locally or in tests.');

        return 1;
    }
    DB::transaction(function () {
        if ($this->option('reset')) {
            $events = Tournament::whereHas('group', fn ($query) => $query->where('name', 'Lokale Testrunde'))
                ->where('name', 'like', 'Lokaler Test%')->get();
            foreach ($events as $event) {
                // Older local databases may lack cascade rules; delete children explicitly.
                $matchIds = $event->matches()->pluck('round_matches.id');
                DB::table('round_match_players')->whereIn('round_match_id', $matchIds)->delete();
                DB::table('round_matches')->whereIn('id', $matchIds)->delete();
                $event->rounds()->delete();
                $event->users()->delete();
                $event->delete();
            }
        }
        $this->call('db:seed', ['--class' => LocalTournamentSeeder::class]);
    });
    $events = Tournament::whereHas('group', fn ($query) => $query->where('name', 'Lokale Testrunde'))
        ->where('name', 'like', 'Lokaler Test%')->withCount('users')->get();
    $this->table(['ID', 'Turnier', 'Personen'], $events->map(fn ($event) => [$event->id, $event->name, $event->users_count])->all());

    return 0;
})->purpose('Seed local Commander and Sealed demo tournaments with the organizer seated');
