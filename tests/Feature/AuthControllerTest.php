<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the login page with the application stylesheet', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('css/app.css', false)
        ->assertSee('Para efetuar o login é necessário seu e-mail e chave de acesso', false);
});

it('redirects a matriz user to the matriz dashboard after login', function () {
    $user = User::factory()->matriz()->create([
        'email' => 'matriz@gmail.com',
        'password' => 'matriz123',
    ]);

    $response = $this->post(route('login.autenticar'), [
        'email' => $user->email,
        'password' => 'matriz123',
    ]);

    $response->assertRedirect(route('matriz'));
    $this->assertAuthenticatedAs($user);
});

it('returns to login with an error when credentials are invalid', function () {
    User::factory()->create();

    $response = $this->from(route('login'))->post(route('login.autenticar'), [
        'email' => 'naoexiste@example.com',
        'password' => 'errada',
    ]);

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rejects a login attempt without email and password', function () {
    $response = $this->from(route('login'))->post(route('login.autenticar'), []);

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email', 'password']);
});

it('logs the user out and redirects to login', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
