<?php

it('sends the root URL to the dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});
