<?php

test('home redirects to admin login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/admin/login');
});
