<?php

namespace App\Devnull\Data;

/**
 * The thread the web shows under a commit in a list: its newest reply or
 * quote, and whether there are more behind it for "Show more".
 */
final readonly class ReplyPreview
{
    /**
     * @param  list<Commit>  $replies
     */
    public function __construct(public array $replies = [], public bool $hasMore = false) {}

    /**
     * Reads `{data, next_cursor}`. The cursor pages the web's newest-first
     * replies, which the API doesn't offer, so only whether it's there counts.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $replies = array_values(array_map(Commit::fromArray(...), array_filter(is_array($data['data'] ?? null) ? $data['data'] : [], is_array(...))));
        $cursor = $data['next_cursor'] ?? null;

        return new self($replies, is_string($cursor) && $cursor !== '');
    }

    public function isEmpty(): bool
    {
        return $this->replies === [];
    }
}
