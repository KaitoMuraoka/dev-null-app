<?php

namespace App\Devnull\Data;

use App\Devnull\Display;
use App\Devnull\Lane;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * A commit as the web shows it. A deleted commit that still has replies comes
 * back as a tombstone: an id, its parent, when it went and its replies count,
 * with no body or author.
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
        public User $user,
        public ?CarbonImmutable $createdAt = null,
        public ?string $parentId = null,
        public int $repliesCount = 0,
        public ?string $url = null,
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
                user: User::fromArray([]),
                parentId: $parentId,
                repliesCount: $repliesCount,
                deletedAt: self::date($data['deleted_at']) ?? CarbonImmutable::now(),
            );
        }

        $user = is_array($data['user'] ?? null) ? $data['user'] : [];

        if (! isset($user['id']) && isset($data['user_id'])) {
            $user['id'] = $data['user_id'];
        }

        $body = (string) ($data['body'] ?? '');
        $mentions = self::listOf($data['mentions'] ?? null, Mention::fromArray(...));
        $segments = is_array($data['body_segments'] ?? null) ? array_values(array_filter($data['body_segments'], is_array(...))) : null;

        return new self(
            id: $id,
            body: $body,
            status: (string) ($data['status'] ?? 'approved'),
            user: User::fromArray($user),
            createdAt: self::date($data['created_at'] ?? null),
            parentId: $parentId,
            repliesCount: $repliesCount,
            url: is_string($data['url'] ?? null) ? $data['url'] : null,
            starsCount: max(0, (int) ($data['stars_count'] ?? 0)),
            starred: ($data['starred'] ?? false) === true,
            isSaved: is_bool($data['is_saved'] ?? null) ? $data['is_saved'] : null,
            isPinned: ($data['is_pinned'] ?? false) === true,
            mentions: $mentions,
            linkPreview: is_array($data['link_preview'] ?? null) ? LinkPreview::fromArray($data['link_preview']) : null,
            images: self::listOf($data['images'] ?? null, CommitImage::fromArray(...)),
            content: CommitBody::blocks($body, $segments, $mentions),
            parent: is_array($data['parent'] ?? null) ? self::fromArray($data['parent']) : null,
        );
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function shortHash(): string
    {
        return Display::shortHash($this->id);
    }

    /**
     * The author's lane, or Rail Gray for a deleted commit, which has no author.
     */
    public function lane(): int|string
    {
        return $this->isDeleted() ? Lane::Rail : $this->user->lane();
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
