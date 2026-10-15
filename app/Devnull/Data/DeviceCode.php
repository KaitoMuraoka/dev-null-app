<?php

namespace App\Devnull\Data;

/**
 * What GitHub hands out to start the device flow: a code for the person to
 * type on github.com, and a code for this app to poll with.
 */
final readonly class DeviceCode
{
    public function __construct(
        public string $deviceCode,
        public string $userCode,
        public string $verificationUri,
        public int $expiresIn = 900,
        public int $interval = 5,
        public ?string $verificationUriComplete = null,
    ) {}

    /**
     * @param  array<string, mixed>  $json
     */
    public static function fromArray(array $json): ?self
    {
        $deviceCode = $json['device_code'] ?? null;
        $userCode = $json['user_code'] ?? null;
        $verificationUri = $json['verification_uri'] ?? null;

        if (! is_string($deviceCode) || $deviceCode === '' || ! is_string($userCode) || $userCode === '' || ! is_string($verificationUri) || $verificationUri === '') {
            return null;
        }

        $complete = $json['verification_uri_complete'] ?? null;

        return new self(
            deviceCode: $deviceCode,
            userCode: $userCode,
            verificationUri: $verificationUri,
            expiresIn: is_numeric($json['expires_in'] ?? null) ? max(1, (int) $json['expires_in']) : 900,
            interval: is_numeric($json['interval'] ?? null) ? max(1, (int) $json['interval']) : 5,
            verificationUriComplete: is_string($complete) && $complete !== '' ? $complete : null,
        );
    }

    /**
     * The page to open: one with the code already filled in, when GitHub offers it.
     */
    public function pageToOpen(): string
    {
        return $this->verificationUriComplete ?? $this->verificationUri;
    }
}
