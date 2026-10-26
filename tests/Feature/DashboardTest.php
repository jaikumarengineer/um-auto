<?php

use App\Models\User;

test('guests are redirected to the admin login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect(route('admin::login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});