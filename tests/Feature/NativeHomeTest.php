<?php

use Native\Mobile\Testing\Native;

it('shows the app name and the configured devnull url', function () {
    config(['devnull.url' => 'https://example.test']);

    Native::visit('/')
        ->assertSee('devnull')
        ->assertSee('https://example.test')
        ->assertMissingElement('top_bar')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'home-screen'
            && ($node['layout']['width'] ?? null) === 'fill'
            && ($node['layout']['height'] ?? null) === 'fill'
            && ($node['layout']['safe_area'] ?? null) === 1)
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'devnull-url');
});

it('takes every color from the native-ui theme tokens', function () {
    $light = config('native-ui.theme.light');
    $dark = config('native-ui.theme.dark');

    Native::visit('/')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'home-screen'
            && ($node['style']['bg_color'] ?? null) === $light['background']
            && ($node['props']['dark_bg_color'] ?? null) === $dark['background'])
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'app-name'
            && ($node['props']['color'] ?? null) === $light['on-background']
            && ($node['props']['dark_color'] ?? null) === $dark['on-background'])
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'devnull-url'
            && ($node['props']['color'] ?? null) === $light['on-surface-variant']
            && ($node['props']['dark_color'] ?? null) === $dark['on-surface-variant']);
});

it('is fully accessible', function () {
    $screen = Native::visit('/')->assertAccessible();

    expect($screen->accessibilityViolations())->toBe([]);
});
