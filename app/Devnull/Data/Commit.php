<?php

namespace App\Devnull\Data;

use App\Devnull\Display;
use Carbon\CarbonImmutable;
use Throwable;

final readonly class Commit
{
    public const int MinLength = 3;

    public const int MaxLength = 280;

    public function __construct(
        public string $id,
        public string $body,
        public string $status,
        public User $user,
        public ?CarbonImmutable $createdAt = null,
        public ?string $parentId = null,
        public int $repliesCount = 0,
        public ?string $url = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];

        if (! isset($user['id']) && isset($data['user_id'])) {
            $user['id'] = $data['user_id'];
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            body: (string) ($data['body'] ?? ''),
            status: (string) ($data['status'] ?? 'approved'),
            user: User::fromArray($user),
            createdAt: self::date($data['created_at'] ?? null),
            parentId: isset($data['parent_id']) ? (string) $data['parent_id'] : null,
            repliesCount: (int) ($data['replies_count'] ?? 0),
            url: is_string($data['url'] ?? null) ? $data['url'] : null,
        );
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

    public function lane(): int
    {
        return $this->user->lane();
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
}
