<?php

namespace App\Devnull;

/**
 * How far onboarding has got: adding the key through GitHub, then, when
 * devnull wants it, approving this phone on the web.
 */
enum GitHubStep: string
{
    /** Nothing asked yet: the Connect GitHub button. */
    case Start = 'start';

    /** A code is on screen and the app asks GitHub, at its pace, whether it was entered. */
    case Authorizing = 'authorizing';

    /** The code ran out before anyone entered it. */
    case Expired = 'expired';

    /** The key is on GitHub; devnull is asked to find it until GitHub lists it publicly. */
    case Listing = 'listing';

    /** devnull found the key but wants the phone approved: a code is on screen and devnull is asked, at its pace, whether it was entered. */
    case Approving = 'approving';
}
