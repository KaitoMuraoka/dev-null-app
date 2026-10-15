<?php

use App\Devnull\Lane;

it('puts authors in the same lane as the web', function (string $id, int $lane) {
    expect(Lane::for($id))->toBe($lane);
})->with([
    // Lanes computed with laneFor() from resources/js/lib/utils.ts.
    ['9b2f6c1e-1d3a-4e5f-8a7b-0c1d2e3f4a5b', 3],
    ['01J9ZX3F7Q8N2K4M6P0R5T7V9X', 4],
    ['local_dev', 5],
    ['1', 1],
    ['naïve-ü', 0],
    ['😀x', 1],
    [str_repeat('a', 40), 4],
]);
