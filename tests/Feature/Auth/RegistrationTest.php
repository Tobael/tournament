<?php

use App\Models\User;

test('public account registration is disabled', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'Uninvited', 'email' => 'test@example.com', 'password' => 'password'])->assertNotFound();
    $this->assertGuest();
});

test('organizers are provisioned explicitly through the console', function () {
    $this->artisan('app:organizer', ['email' => 'organizer@example.com', '--name' => 'Organizer'])
        ->expectsQuestion('Password (at least 12 characters)', 'a-long-test-password')
        ->expectsQuestion('Confirm password', 'a-long-test-password')
        ->assertSuccessful();
    expect(User::where('email', 'organizer@example.com')->first()->is_admin)->toBeTruthy();
});
