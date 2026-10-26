<?php

test('registration route is not available', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});
