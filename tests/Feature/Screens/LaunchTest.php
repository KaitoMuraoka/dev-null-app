<?php

use Native\Mobile\Testing\Native;

it('sends a new device to onboarding', function () {
    fakeKeychain();

    Native::visit('/')->assertReplacedWith('/onboarding');
});

it('sends a verified device straight to the log', function () {
    signIn();

    Native::visit('/')->assertReplacedWith('/log');
});
