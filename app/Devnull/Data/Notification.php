<?php

namespace App\Devnull\Data;

use Carbon\CarbonImmutable;

final readonly class Notification
{
    public function __construct(
        public string $id,
        public string $type,
        public User $actor,
        public ?CarbonImmutable $createdAt = null,
        public bool $isUnread = false,
        public ?string $commitId = null,
        public ?string $body = null,
        public ?string $parentBody = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $commit = is_array($data['commit'] ?? null) ? $data['commit'] : [];
        $parent = is_array($commit['parent'] ?? null) ? $commit['parent'] : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            actor: User::fromArray(is_array($data['actor'] ?? null) ? $data['actor'] : []),
            createdAt: Commit::date($data['created_at'] ?? null),
            isUnread: array_key_exists('read_at', $data) && $data['read_at'] === null,
            commitId: isset($commit['id']) ? (string) $commit['id'] : (isset($data['commit_id']) ? (string) $data['commit_id'] : null),
            body: isset($commit['body']) ? (string) $commit['body'] : null,
            parentBody: isset($parent['body']) ? (string) $parent['body'] : null,
        );
    }

    public function isReply(): bool
    {
        return $this->type === 'replied' && $this->commitId !== null;
    }
}
