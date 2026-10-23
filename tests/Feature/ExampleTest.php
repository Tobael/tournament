<?php

test('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertOk()->assertSee('Ohne Anmeldung');
});
