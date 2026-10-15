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
     * Reads a `GET /users/{username}` response: the user and counts in `data`,
     * and whether you follow them alongside it.
     *
     * @param  array<string, mixed>  $json
     */
    public static function fromArray(array $json): self
    {
        $data = is_array($json['data'] ?? null) ? $json['data'] : $json;
        $counts = is_array($data['counts'] ?? null) ? $data['counts'] : [];

        return new self(
            user: User::fromArray(is_array($data['user'] ?? null) ? $data['user'] : $data),
            commitsCount: (int) ($data['commits_count'] ?? $counts['commits'] ?? 0),
            followersCount: (int) ($data['followers_count'] ?? $counts['followers'] ?? 0),
            followingCount: (int) ($data['following_count'] ?? $counts['following'] ?? 0),
            isFollowing: (bool) ($json['is_following'] ?? $data['is_following'] ?? false),
            followsYou: (bool) ($json['follows_you'] ?? $data['follows_you'] ?? false),
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
