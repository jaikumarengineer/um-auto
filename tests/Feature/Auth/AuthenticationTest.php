<?php

use App\Models\User;
use Livewire\Volt\Volt as LivewireVolt;

test('login redirects to admin auth', function () {
    $response = $this->get('/login');

    $response->assertRedirect(route('admin::login'));
});

test('home redirects to admin auth', function () {
    $response = $this->get('/');

    $response->assertRedirect('/admin/login');
});

test('users can authenticate using the admin login screen', function () {
    $user = User::factory()->create([
        'user_type' => 'admin',
    ]);

    $response = LivewireVolt::test('admin.auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route($user->adminHomeRoute(), absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = LivewireVolt::test('admin.auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login');

    $response->assertHasErrors('email');

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect(route('admin::login'));

    $this->assertGuest();
});
