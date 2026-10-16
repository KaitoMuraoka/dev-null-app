<?php

it('ships the logomark as a 1024px icon with no transparency', function () {
    $path = public_path('icon.png');

    expect($path)->toBeFile();

    [$width, $height, $type] = getimagesize($path);

    // Byte 25 of a PNG is the IHDR colour type: 2 is truecolour without an alpha channel.
    $colourType = ord((string) file_get_contents($path, length: 26)[25]);

    expect([$width, $height])->toBe([1024, 1024])
        ->and($type)->toBe(IMAGETYPE_PNG)
        ->and($colourType)->toBe(2);
});

it('draws the logomark on the light shell colour', function () {
    $image = imagecreatefrompng(public_path('icon.png'));

    $corner = imagecolorsforindex($image, imagecolorat($image, 8, 8));

    expect(sprintf('#%02x%02x%02x', $corner['red'], $corner['green'], $corner['blue']))->toBe('#f6f7f8');
});
