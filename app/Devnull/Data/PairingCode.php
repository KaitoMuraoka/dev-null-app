<?php

namespace App\Devnull\Data;

use Carbon\CarbonImmutable;

/**
 * What devnull answers a key sync with while this phone isn't approved yet:
 * a code for the person to enter on the web, where to enter it, and when it
 * runs out. Asking again gets the same code until then, and a new one after.
 */
final readonly class PairingCode
{
    public function __construct(
        public string $code,
        public string $approvalUrl,
        public CarbonImmutable $expiresAt,
        public string $message = '',
    ) {}

    /**
     * @param  array<string, mixed>  $json
     */
    public static function fromArray(array $json): ?self
    {
        $code = $json['pairing_code'] ?? null;
        $approvalUrl = $json['approval_url'] ?? null;

        if (! is_string($code) || $code === '' || ! is_string($approvalUrl) || $approvalUrl === '') {
            return null;
        }

        $message = $json['message'] ?? null;

        return new self(
            code: $code,
            approvalUrl: $approvalUrl,
            expiresAt: Commit::date($json['expires_at'] ?? null) ?? CarbonImmutable::now()->addMinutes(15),
            message: is_string($message) ? $message : '',
        );
    }
}
