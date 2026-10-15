<?php

namespace App\Devnull\Data;

final readonly class Profile
{
    public function __construct(
        public User $user,
        public int $commitsCount = 0,
        public int $followersCount = 0,
        public int $followingCount = 0,
        public bool $isFollowing = false,
        public bool $followsYou = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $counts = is_array($data['counts'] ?? null) ? $data['counts'] : [];

        return new self(
            user: User::fromArray(is_array($data['user'] ?? null) ? $data['user'] : $data),
            commitsCount: (int) ($data['commits_count'] ?? $counts['commits'] ?? 0),
            followersCount: (int) ($data['followers_count'] ?? $counts['followers'] ?? 0),
            followingCount: (int) ($data['following_count'] ?? $counts['following'] ?? 0),
            isFollowing: (bool) ($data['is_following'] ?? $data['following'] ?? false),
            followsYou: (bool) ($data['follows_you'] ?? false),
        );
    }

    public function withFollowing(bool $isFollowing): self
    {
        $change = $isFollowing === $this->isFollowing ? 0 : ($isFollowing ? 1 : -1);

        return new self(
            $this->user,
            $this->commitsCount,
            max(0, $this->followersCount + $change),
            $this->followingCount,
            $isFollowing,
            $this->followsYou,
        );
    }
}
