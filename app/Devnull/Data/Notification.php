<?php

namespace App\Devnull\Data;

use Carbon\CarbonImmutable;

/**
 * A follow, a reply, a mention, or a moderator's verdict on your commit. The
 * commit's parent only comes along when you may see it.
 */
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
        public ?string $reason = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $commit = is_array($data['commit'] ?? null) ? $data['commit'] : [];
        $parent = is_array($commit['parent'] ?? null) ? $commit['parent'] : [];
        $reason = $data['reason'] ?? null;

        return new self(
            id: (string) ($data['id'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            actor: User::fromArray(is_array($data['actor'] ?? null) ? $data['actor'] : []),
            createdAt: Commit::date($data['created_at'] ?? null),
            isUnread: array_key_exists('read_at', $data) && $data['read_at'] === null,
            commitId: isset($commit['id']) ? (string) $commit['id'] : (isset($data['commit_id']) ? (string) $data['commit_id'] : null),
            body: isset($commit['body']) ? (string) $commit['body'] : null,
            parentBody: isset($parent['body']) ? (string) $parent['body'] : null,
            reason: is_string($reason) && trim($reason) !== '' ? $reason : null,
        );
    }

    public function isReply(): bool
    {
        return $this->type === 'replied' && $this->commitId !== null;
    }

    public function isMention(): bool
    {
        return $this->type === 'mentioned';
    }

    /**
     * A moderator approved or rejected your commit. Nobody else is named.
     */
    public function isModeration(): bool
    {
        return in_array($this->type, ['approved', 'rejected'], true);
    }

    public function isRejection(): bool
    {
        return $this->type === 'rejected';
    }

    /**
     * Everything but a follow is about a commit, and opens it.
     */
    public function opensCommit(): bool
    {
        return $this->type !== 'followed' && $this->commitId !== null;
    }

    /**
     * What happened, as the web words it. For a person it follows their name.
     */
    public function sentence(): string
    {
        return match ($this->type) {
            'followed' => 'followed you',
            'mentioned' => 'mentioned you',
            'approved', 'rejected' => "Your commit was {$this->type}",
            default => 'replied to your commit',
        };
    }
}
