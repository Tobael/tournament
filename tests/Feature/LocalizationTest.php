<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

test('German is the default on public pages and all global branding is Arcane Table', function () {
    $this->get('/?home=1')->assertOk()->assertSee('lang="de"', false)->assertSee('Deine Runde.')->assertSee('Arcane Table');
    $this->get('/login')->assertOk()->assertSee('Organisation anmelden')->assertSee('Passwort');
    $this->get('/tv')->assertOk()->assertSee('lang="de"', false)->assertSee('Arcane Table');
});

test('English selection persists across public authenticated and API responses', function () {
    $this->from('/?home=1')->post(route('language'), ['locale' => 'en'])->assertRedirect('/?home=1')->assertSessionHas('locale', 'en')->assertCookie('arcane_locale', 'en');
    $this->get('/?home=1')->assertSee('lang="en"', false)->assertSee('Your table.');
    $this->get('/login')->assertSee('Organizer login')->assertSee('Password');
    $this->actingAs(User::factory()->create())->get('/tournaments')->assertOk()->assertSee('Together at the table.')->assertSee('All tournaments');
    $this->post(route('language'), ['locale' => 'de'])->assertSessionHas('locale', 'de');
});

test('invalid languages cannot be stored and language redirects stay on this host', function () {
    $this->post(route('language'), ['locale' => '../../secret'])->assertSessionHasErrors('locale')->assertSessionMissing('locale');
    $this->withHeaders(['referer' => 'https://other.example/path'])->post(route('language'), ['locale' => 'en'])->assertRedirect(route('home', ['home' => 1]));
});

test('a remembered language cookie works without a previous session', function () {
    $this->withCookie('arcane_locale', 'en')->get('/?home=1')->assertSee('lang="en"', false)->assertSee('Your table.');
});

test('validation errors default to German', function () {
    $this->getJson('/join')->assertUnprocessable()->assertJsonPath('errors.invite.0', 'Einladung ist erforderlich.');
});
