<?php

namespace App\Devnull;

/**
 * A GET signed with this device's key, ready to send from anywhere: the full
 * URL as signed, and the headers carrying the signature. The signature is for
 * this one request, with its own nonce and a timestamp devnull only accepts
 * for a few minutes, and it's never kept.
 */
final readonly class SignedRequest
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(public string $url, public array $headers) {}
}
