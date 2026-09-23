<?php

test('the dashboard redirects an anonymous visitor to login', function () {
    // `/` is the admin dashboard and sits behind the web `auth` middleware, so an
    // anonymous GET is expected to redirect rather than render.
    $this->get('/')->assertRedirect(route('login'));
});
