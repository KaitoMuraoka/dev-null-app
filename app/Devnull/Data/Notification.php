<?php

namespace App\Devnull\Data;

use Carbon\CarbonImmutable;

/**
 * A group of notifications, as the web shows it: the newest one, with the
 * first few people in the group, how many there are and how many of them are
 * unread. Stars and replies on one of your commits group together; a follow,
 * a mention and a moderator's verdict each stand alone. The commit's parent
 * only comes along when you may see it.
 */
final readonly class Notification
{
    /**
     * @param  list<User>  $actors  the newest first, up to three
     */
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
        public ?string $subjectId = null,
        public array $actors = [],
        public int $actorsCount = 1,
        public int $unreadCount = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $commit = is_array($data['commit'] ?? null) ? $data['commit'] : [];
        $parent = is_array($commit['parent'] ?? null) ? $commit['parent'] : [];
        $reason = $data['reason'] ?? null;
        $actor = User::fromArray(is_array($data['actor'] ?? null) ? $data['actor'] : []);
        $actors = array_values(array_map(User::fromArray(...), array_filter(is_array($data['actors'] ?? null) ? $data['actors'] : [], is_array(...))));
        $isUnread = array_key_exists('unread_count', $data)
            ? (int) $data['unread_count'] > 0
            : array_key_exists('read_at', $data) && $data['read_at'] === null;

        return new self(
            id: (string) ($data['id'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            actor: $actor,
            createdAt: Commit::date($data['created_at'] ?? null),
            isUnread: $isUnread,
            commitId: isset($commit['id']) ? (string) $commit['id'] : (isset($data['commit_id']) ? (string) $data['commit_id'] : null),
            body: isset($commit['body']) ? (string) $commit['body'] : null,
            parentBody: isset($parent['body']) ? (string) $parent['body'] : null,
            reason: is_string($reason) && trim($reason) !== '' ? $reason : null,
            subjectId: is_string($data['subject_id'] ?? null) && $data['subject_id'] !== '' ? $data['subject_id'] : null,
            actors: $actors === [] ? [$actor] : $actors,
            actorsCount: max(1, count($actors), (int) ($data['actors_count'] ?? 1)),
            unreadCount: max(0, (int) ($data['unread_count'] ?? ($isUnread ? 1 : 0))),
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

    public function isStar(): bool
    {
        return $this->type === 'starred';
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
     * The commit a tap opens, as on the web: the starred commit, the reply, or
     * for replies from several people your commit they replied to. A follow
     * opens a profile instead.
     */
    public function commitToOpen(): ?string
    {
        return match (true) {
            $this->type === 'followed' => null,
            $this->type === 'replied' && $this->actorsCount > 1 => $this->subjectId ?? $this->commitId,
            default => $this->commitId,
        };
    }

    /**
     * Everything but a follow is about a commit, and opens it.
     */
    public function opensCommit(): bool
    {
        return $this->commitToOpen() !== null;
    }

    /**
     * The people after the first, as the web adds them: " and 1 other", " and 3 others".
     */
    public function others(): string
    {
        $others = $this->actorsCount - 1;

        return $others < 1 ? '' : " and {$others} ".($others === 1 ? 'other' : 'others');
    }

    /**
     * What happened, as the web words it. For a person it follows their name and the others.
     */
    public function sentence(): string
    {
        return match ($this->type) {
            'followed' => 'followed you',
            'mentioned' => 'mentioned you',
            'starred' => 'starred your commit',
            'approved', 'rejected' => "Your commit was {$this->type}",
            default => 'replied to your commit',
        };
    }
}
