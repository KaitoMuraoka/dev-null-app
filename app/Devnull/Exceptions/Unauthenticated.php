<?php

namespace App\Devnull\Exceptions;

/**
 * The device key isn't (or is no longer) on a devnull user's GitHub account.
 */
final class Unauthenticated extends ApiException {}
