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

    /*
    |--------------------------------------------------------------------------
    | Sign in with Apple
    |--------------------------------------------------------------------------
    |
    | Sign in with Apple needs the app's App ID to have that capability, and a
    | provisioning profile that carries it. Turn it off to build for a phone
    | with a wildcard profile: the social auth plugin and its entitlement are
    | left out of the build, and the app offers no Apple buttons.
    |
    */

    'apple_sign_in' => (bool) env('DEVNULL_APPLE_SIGN_IN', true),

];
