<?php

namespace App\Devnull\Exceptions;

use App\Devnull\ErrorCode;

/**
 * devnull doesn't know this phone's key, or knows it but the phone isn't
 * approved as a device.
 */
final class Unauthenticated extends ApiException
{
    /**
     * Whether devnull only wants this phone approved, which signing in again
     * on dev-null.sh does.
     */
    public function needsApproval(): bool
    {
        return $this->is(ErrorCode::DeviceNotApproved);
    }
}
