<?php

use App\Models\RadarMind\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $unidade = Unit::create(['code' => 'MAT', 'name' => 'Matriz']);
    $this->matriz = User::factory()->matriz()->create(['email' => 'matriz@teste.com', 'password' => 'senha-certa', 'unit_id' => $unidade->id]);
});

it('blocks the login after five wrong attempts, even with the right password', function () {
    foreach (range(1, 5) as $tentativa) {
        $this->post(route('login.autenticar'), ['email' => 'matriz@teste.com', 'password' => 'errada'])
            ->assertSessionHasErrors(['email' => 'E-mail ou chave de acesso inválidos.']);
    }

    $this->post(route('login.autenticar'), ['email' => 'matriz@teste.com', 'password' => 'senha-certa'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toStartWith('Muitas tentativas de acesso.');
    $this->assertGuest();
});

it('logs in normally after a few wrong attempts', function () {
    $this->post(route('login.autenticar'), ['email' => 'matriz@teste.com', 'password' => 'errada']);

    $this->post(route('login.autenticar'), ['email' => 'matriz@teste.com', 'password' => 'senha-certa'])
        ->assertRedirect(route('matriz'));

    $this->assertAuthenticatedAs($this->matriz);
});
