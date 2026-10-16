<?php

namespace App\Devnull\Data;

/**
 * A `GET /me` answer: who this device is signed in as, how many of their
 * notifications are still unread, and whether an Apple ID is linked to them.
 */
final readonly class CurrentUser
{
    public function __construct(public User $user, public int $unreadNotificationsCount = 0, public bool $appleLinked = false) {}

    /**
     * @param  array<string, mixed>  $json
     */
    public static function fromArray(array $json): self
    {
        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $unread = $json['unread_notifications_count'] ?? $data['unread_notifications_count'] ?? 0;

        return new self(User::fromArray($data), is_int($unread) ? max(0, $unread) : 0, ($json['apple_linked'] ?? false) === true);
    }
}
