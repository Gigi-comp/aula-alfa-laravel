<?php

test('guests are redirected from the home page to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
