<?php

namespace App\Devnull;

/**
 * Signs devnull API requests with the device key.
 *
 * The signed message is the timestamp, nonce, uppercase method, request
 * target (path plus query exactly as sent) and raw body, joined by "\n".
 */
final readonly class RequestSigner
{
    public const string Namespace = 'devnull-api';

    public function __construct(private SshKey $key) {}

    public static function message(string $timestamp, string $nonce, string $method, string $target, string $body): string
    {
        return implode("\n", [$timestamp, $nonce, strtoupper($method), $target, $body]);
    }

    public static function nonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @return array{X-Devnull-Timestamp: string, X-Devnull-Nonce: string, X-SSH-Signature: string}
     */
    public function headers(string $method, string $target, string $body, int $timestamp, string $nonce): array
    {
        return [
            'X-Devnull-Timestamp' => (string) $timestamp,
            'X-Devnull-Nonce' => $nonce,
            'X-SSH-Signature' => $this->key->sign(self::message((string) $timestamp, $nonce, $method, $target, $body), self::Namespace),
        ];
    }
}
