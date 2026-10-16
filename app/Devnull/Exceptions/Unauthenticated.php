<?php

namespace App\Devnull\Exceptions;

/**
 * devnull doesn't know this phone's key, or knows it but the phone isn't
 * approved as a device.
 */
final class Unauthenticated extends ApiException
{
    public const string DeviceNotApproved = 'device_not_approved';

    /**
     * Whether devnull only wants this phone approved, which signing in again
     * on dev-null.sh does.
     */
    public function needsApproval(): bool
    {
        return $this->errorCode === self::DeviceNotApproved;
    }
}
