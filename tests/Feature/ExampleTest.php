<?php

test('la dirección principal lleva al panel', function () {
    $this->get('/')->assertRedirect('/admin');
});
