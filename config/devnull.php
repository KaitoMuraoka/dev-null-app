<?php

return [

    /*
    |--------------------------------------------------------------------------
    | devnull URL
    |--------------------------------------------------------------------------
    |
    | The base URL of the devnull web app this client talks to. Point it at a
    | local copy while developing and leave it unset to use production.
    |
    */

    'url' => env('DEVNULL_URL', 'https://dev-null.sh'),

    /*
    |--------------------------------------------------------------------------
    | GitHub App
    |--------------------------------------------------------------------------
    |
    | The client ID of the GitHub App that adds this phone's signing key to
    | GitHub through the device flow. It isn't a secret, and the device flow
    | needs no secret. Set it to an empty value and onboarding only offers
    | adding the key by hand.
    |
    */

    'github_client_id' => env('DEVNULL_GITHUB_CLIENT_ID', 'Iv23liHvp8jmyOkojtO1'),

];
