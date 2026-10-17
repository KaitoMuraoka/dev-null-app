<?php

namespace App\Devnull\Data;

use App\Devnull\Lane;
use Carbon\CarbonImmutable;

/**
 * A `GET /me` answer: who this device is signed in as, how many of their
 * notifications are still unread, and whether an Apple ID is linked to them.
 *
 * It also has what only you see in the web's profile settings: the lane you
 * chose (null for Automatic) and the one Automatic gives you, your GitHub
 * Sponsors page, and when your profile was last synced from GitHub (null when
 * it only was when you signed in).
 */
final readonly class CurrentUser
{
    public function __construct(
        public User $user,
        public int $unreadNotificationsCount = 0,
        public bool $appleLinked = false,
        public ?int $preferredLane = null,
        public int $automaticLane = 0,
        public ?string $sponsorsUrl = null,
        public ?CarbonImmutable $githubSyncedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $json
     */
    public static function fromArray(array $json): self
    {
        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $unread = $json['unread_notifications_count'] ?? $data['unread_notifications_count'] ?? 0;
        $user = User::fromArray($data);
        $automaticLane = Lane::from($data['automatic_lane'] ?? null) ?? Lane::for($user->id);
        $sponsorsUrl = $data['sponsors_url'] ?? null;

        return new self(
            user: $user,
            unreadNotificationsCount: is_int($unread) ? max(0, $unread) : 0,
            appleLinked: ($json['apple_linked'] ?? false) === true,
            // Without devnull's word on it, your lane is Automatic when it's the one Automatic would give you.
            preferredLane: array_key_exists('preferred_lane', $data)
                ? Lane::from($data['preferred_lane'])
                : ($user->lane() === $automaticLane ? null : $user->lane()),
            automaticLane: $automaticLane,
            sponsorsUrl: is_string($sponsorsUrl) && preg_match('#^https://#i', $sponsorsUrl) === 1 ? $sponsorsUrl : null,
            githubSyncedAt: Commit::date($data['github_synced_at'] ?? null),
        );
    }

    /**
     * You as this phone remembers you, until devnull says the rest.
     */
    public static function remembered(User $user): self
    {
        return self::fromArray(['data' => $user->toArray()]);
    }

    /**
     * Your GitHub Sponsors page as the web prints it, without the scheme.
     */
    public function sponsorsLabel(): ?string
    {
        return $this->sponsorsUrl === null ? null : (string) preg_replace('#^https://#i', '', $this->sponsorsUrl);
    }
}
