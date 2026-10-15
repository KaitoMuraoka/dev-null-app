<?php

namespace App\Devnull\Data;

use SensitiveParameter;

/**
 * One answer from GitHub's token endpoint while the device flow runs.
 *
 * Only a granted answer carries a token. It's meant to be used straight away
 * and dropped, so it never shows up in dumps or stack traces.
 */
final readonly class DeviceAuthorization
{
    private function __construct(
        public AuthorizationStatus $status,
        #[SensitiveParameter] public ?string $token = null,
        public ?int $interval = null,
    ) {}

    public static function granted(#[SensitiveParameter] string $token): self
    {
        return new self(AuthorizationStatus::Granted, $token);
    }

    public static function of(AuthorizationStatus $status, ?int $interval = null): self
    {
        return new self($status, interval: $interval);
    }

    /**
     * @return array{status: string, interval: ?int}
     */
    public function __debugInfo(): array
    {
        return ['status' => $this->status->value, 'interval' => $this->interval];
    }
}
