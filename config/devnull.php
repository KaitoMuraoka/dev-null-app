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
    | GitHub OAuth app
    |--------------------------------------------------------------------------
    |
    | The client ID of the OAuth app that adds this phone's signing key to
    | GitHub through the device flow. The device flow needs no secret. Leave
    | it unset and onboarding only offers adding the key by hand.
    |
    */

    'github_client_id' => env('DEVNULL_GITHUB_CLIENT_ID'),

];
