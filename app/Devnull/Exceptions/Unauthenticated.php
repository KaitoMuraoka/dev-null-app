<?php

namespace App\Devnull\Exceptions;

/**
 * The device key isn't (or is no longer) on a devnull user's GitHub account,
 * or it is but the phone hasn't been approved as a device.
 */
final class Unauthenticated extends ApiException
{
    /**
     * Whether devnull only wants this phone approved, which a pairing code
     * fixes. It says so in words: "This key isn't approved for the devnull
     * app yet."
     */
    public function needsApproval(): bool
    {
        return str_contains(strtolower($this->getMessage()), 'approved');
    }
}
