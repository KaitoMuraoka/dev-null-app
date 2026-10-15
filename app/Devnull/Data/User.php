<?php

namespace App\Devnull\Data;

use App\Devnull\Lane;

final readonly class User
{
    public function __construct(
        public string $id,
        public string $username,
        public string $name,
        public ?string $avatarUrl = null,
        public ?string $bio = null,
        public ?int $lane = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $username = (string) ($data['username'] ?? '');
        $id = $data['id'] ?? null;
        $name = $data['name'] ?? null;

        return new self(
            id: is_scalar($id) && (string) $id !== '' ? (string) $id : $username,
            username: $username,
            name: is_string($name) && $name !== '' ? $name : $username,
            avatarUrl: is_string($data['avatar_url'] ?? null) ? $data['avatar_url'] : null,
            bio: is_string($data['bio'] ?? null) && $data['bio'] !== '' ? $data['bio'] : null,
            lane: Lane::from($data['lane'] ?? null),
        );
    }

    /**
     * @return array{id: string, username: string, name: string, avatar_url: ?string, bio: ?string, lane: ?int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'avatar_url' => $this->avatarUrl,
            'bio' => $this->bio,
            'lane' => $this->lane,
        ];
    }

    /**
     * The lane the user picked on the web, or the one devnull gave them.
     * Hashing the id is only for a server too old to send it.
     */
    public function lane(): int
    {
        return $this->lane ?? Lane::for($this->id);
    }

    public function handle(): string
    {
        return '@'.$this->username;
    }
}
