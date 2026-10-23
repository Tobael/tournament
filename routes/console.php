<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
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
