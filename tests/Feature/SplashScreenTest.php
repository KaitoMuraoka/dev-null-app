<?php

use App\Providers\NativeServiceProvider;
use S2BR\MobileSplashscreen\MobileSplashscreen;
use S2BR\MobileSplashscreen\MobileSplashscreenServiceProvider;

/**
 * The Lottie animation inside a dotLottie v1 archive, which is what lottie-spm reads on iOS.
 *
 * @return array<string, mixed>
 */
function splashAnimation(string $path): array
{
    $archive = new ZipArchive;

    expect($archive->open(base_path($path)))->toBeTrue("Can't open {$path}.");

    $manifest = json_decode((string) $archive->getFromName('manifest.json'), true);
    $animation = json_decode((string) $archive->getFromName('animations/main.json'), true);
    $archive->close();

    expect($manifest['version'])->toBe('1.0')
        ->and($manifest['animations'][0]['id'])->toBe('main')
        ->and($animation)->toBeArray();

    return $animation;
}

/**
 * Every fill and stroke colour in an animation's shape groups, as hex.
 *
 * @param  array<string, mixed>  $animation
 * @return list<string>
 */
function splashColours(array $animation): array
{
    $colours = [];

    foreach ($animation['layers'] as $layer) {
        foreach ($layer['shapes'] as $group) {
            foreach ($group['it'] as $item) {
                if (in_array($item['ty'], ['fl', 'st'], true)) {
                    [$red, $green, $blue] = $item['c']['k'];
                    $colours[] = sprintf('#%02x%02x%02x', round($red * 255), round($green * 255), round($blue * 255));
                }
            }
        }
    }

    sort($colours);

    return $colours;
}

it('registers the splash screen plugin', function () {
    expect((new NativeServiceProvider(app()))->plugins())->toContain(MobileSplashscreenServiceProvider::class)
        ->and(app(MobileSplashscreen::class)->validate())->toBe([]);
});

it('plays the logo once on the shell colour, light and dark', function () {
    expect(config('mobile-splashscreen.content'))->toBe('animation')
        ->and(config('mobile-splashscreen.animation.loop'))->toBeFalse()
        ->and(config('mobile-splashscreen.background'))->toMatchArray(['type' => 'color', 'color' => '#f6f7f8'])
        ->and(config('mobile-splashscreen.theme.mode'))->toBe('auto')
        ->and(config('mobile-splashscreen.theme.dark.background'))->toMatchArray(['type' => 'color', 'color' => '#111112']);

    $light = splashAnimation(config('mobile-splashscreen.animation.path'));
    $dark = splashAnimation(config('mobile-splashscreen.theme.dark.animation_path'));

    // Ink and lane-0, as DESIGN.md has them.
    expect(splashColours($light))->toBe(['#121417', '#d9480f'])
        ->and(splashColours($dark))->toBe(['#ececec', '#ff8a4c']);

    foreach ([$light, $dark] as $animation) {
        // A second, then it holds on the last frame until the app is ready.
        expect(($animation['op'] - $animation['ip']) / $animation['fr'])->toBe(1)
            ->and(array_column($animation['layers'], 'nm'))->toBe(['lane-0 outline', 'ink slash']);
    }
});

it('ships launch images for both appearances', function (string $file) {
    [$width, $height] = getimagesize(public_path($file));

    // NativePHP wants at least 1080 x 1920.
    expect($width)->toBeGreaterThanOrEqual(1080)
        ->and($height)->toBeGreaterThanOrEqual(1920);
})->with(['splash.png', 'splash-dark.png']);
