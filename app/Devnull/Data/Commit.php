<?php

namespace App\Devnull\Data;

use App\Devnull\Display;
use App\Devnull\Lane;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * A commit as the web shows it, with its author and, for a part of a series,
 * its place in it. A deleted commit that still has replies comes back as a
 * tombstone: an id, its parent, when it went, its replies count and its place
 * in a series, with no body or author.
 */
final readonly class Commit
{
    public const int MinLength = 3;

    public const int MaxLength = 280;

    /**
     * @param  list<Mention>  $mentions
     * @param  list<CommitImage>  $images
     * @param  list<BodyBlock>  $content
     */
    public function __construct(
        public string $id,
        public string $body,
        public string $status,
        public User $author,
        public ?CarbonImmutable $createdAt = null,
        public ?string $parentId = null,
        public int $repliesCount = 0,
        public ?string $htmlUrl = null,
        public ?CarbonImmutable $deletedAt = null,
        public int $starsCount = 0,
        public bool $starred = false,
        public ?bool $isSaved = null,
        public bool $isPinned = false,
        public array $mentions = [],
        public ?LinkPreview $linkPreview = null,
        public array $images = [],
        public array $content = [],
        public ?Commit $parent = null,
        public string $shortHash = '',
        public ?string $seriesId = null,
        public ?int $seriesPosition = null,
        public ?int $seriesSize = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $id = (string) ($data['id'] ?? '');
        $parentId = isset($data['parent_id']) ? (string) $data['parent_id'] : null;
        $repliesCount = (int) ($data['replies_count'] ?? 0);

        if (is_string($data['deleted_at'] ?? null)) {
            return new self(
                id: $id,
                body: '',
                status: '',
                author: User::fromArray([]),
                parentId: $parentId,
                repliesCount: $repliesCount,
                deletedAt: self::date($data['deleted_at']) ?? CarbonImmutable::now(),
                shortHash: Display::shortHash($id),
                seriesPosition: self::positiveInt($data['series_position'] ?? null),
                seriesSize: self::positiveInt($data['series_size'] ?? null),
            );
        }

        $body = (string) ($data['body'] ?? '');
        $mentions = self::listOf($data['mentions'] ?? null, Mention::fromArray(...));
        $segments = is_array($data['body_segments'] ?? null) ? array_values(array_filter($data['body_segments'], is_array(...))) : null;

        return new self(
            id: $id,
            body: $body,
            status: (string) ($data['status'] ?? 'approved'),
            author: User::fromArray(is_array($data['author'] ?? null) ? $data['author'] : []),
            createdAt: self::date($data['created_at'] ?? null),
            parentId: $parentId,
            repliesCount: $repliesCount,
            htmlUrl: is_string($data['html_url'] ?? null) ? $data['html_url'] : null,
            starsCount: max(0, (int) ($data['stars_count'] ?? 0)),
            starred: ($data['starred'] ?? false) === true,
            isSaved: is_bool($data['is_saved'] ?? null) ? $data['is_saved'] : null,
            isPinned: ($data['is_pinned'] ?? false) === true,
            mentions: $mentions,
            linkPreview: is_array($data['link_preview'] ?? null) ? LinkPreview::fromArray($data['link_preview']) : null,
            images: self::listOf($data['images'] ?? null, CommitImage::fromArray(...)),
            content: CommitBody::blocks($body, $segments, $mentions),
            parent: is_array($data['parent'] ?? null) ? self::fromArray($data['parent']) : null,
            shortHash: is_string($data['short_hash'] ?? null) && $data['short_hash'] !== '' ? $data['short_hash'] : Display::shortHash($id),
            seriesId: is_string($data['series_id'] ?? null) ? $data['series_id'] : null,
            seriesPosition: self::positiveInt($data['series_position'] ?? null),
            seriesSize: self::positiveInt($data['series_size'] ?? null),
        );
    }

    /**
     * This commit with your star flipped and its count moved to match.
     */
    public function withStar(bool $starred): self
    {
        $change = $starred === $this->starred ? 0 : ($starred ? 1 : -1);

        return new self(...[...get_object_vars($this), 'starred' => $starred, 'starsCount' => max(0, $this->starsCount + $change)]);
    }

    public function withSaved(bool $isSaved): self
    {
        return new self(...[...get_object_vars($this), 'isSaved' => $isSaved]);
    }

    /**
     * This commit with the star and save state of a fresher copy of it, keeping
     * everything else, such as the parent a list loaded with it.
     */
    public function withReactionsFrom(self $fresh): self
    {
        return new self(...[
            ...get_object_vars($this),
            'starred' => $fresh->starred,
            'starsCount' => $fresh->starsCount,
            'isSaved' => $fresh->isSaved ?? $this->isSaved,
        ]);
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * One of a series of commits posted together, each a reply to the part before.
     * A deleted part keeps its place, so it knows its position without a series id.
     */
    public function isSeriesPart(): bool
    {
        return $this->seriesPosition !== null && $this->seriesSize !== null;
    }

    /**
     * How many parts of its series come after this one, for the web's
     * "2 more commits in this series" under a part shown on its own.
     */
    public function laterSeriesParts(): int
    {
        return $this->isSeriesPart() ? max(0, $this->seriesSize - $this->seriesPosition) : 0;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * The author's lane, or Rail Gray for a deleted commit, which has no author.
     */
    public function lane(): int|string
    {
        return $this->isDeleted() ? Lane::Rail : $this->author->lane();
    }

    public static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private static function positiveInt(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }

    /**
     * @template TItem
     *
     * @param  callable(array<string, mixed>): ?TItem  $map
     * @return list<TItem>
     */
    private static function listOf(mixed $items, callable $map): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map($map, array_filter($items, is_array(...))), fn (mixed $item): bool => $item !== null));
    }
}
