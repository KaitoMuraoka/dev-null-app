<?php

use App\Devnull\Client;

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

    'url' => env('DEVNULL_URL', Client::ProductionUrl),

    /*
    |--------------------------------------------------------------------------
    | Device key store
    |--------------------------------------------------------------------------
    |
    | Where this phone's key is kept: "keychain" needs the paid Secure Storage
    | plugin, "file" is an encrypted file for building without it. Leave it
    | unset and the app decides on the phone: the Keychain whenever the plugin
    | is in the build, the file in a simulator, and an error on a real phone.
    |
    */

    'key_store' => env('DEVNULL_KEY_STORE'),

];
