<?php

namespace App\Devnull\Data;

/**
 * An image attached to a commit.
 */
final readonly class CommitImage
{
    /**
     * The tallest a single image gets, as width over height. The web caps an
     * image's height the same way so a long screenshot doesn't take the feed.
     */
    public const float TallestRatio = 0.75;

    public function __construct(public string $id, public string $url, public int $width = 0, public int $height = 0) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $url = $data['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return null;
        }

        return new self(
            id: (string) ($data['id'] ?? $url),
            url: $url,
            width: is_numeric($data['width'] ?? null) ? max(0, (int) $data['width']) : 0,
            height: is_numeric($data['height'] ?? null) ? max(0, (int) $data['height']) : 0,
        );
    }

    /**
     * Width over height, so the space is held before the image loads, or if it never does.
     */
    public function ratio(): float
    {
        if ($this->width === 0 || $this->height === 0) {
            return 1.0;
        }

        return round(max(self::TallestRatio, $this->width / $this->height), 4);
    }
}
