<?php

namespace App\Devnull\Data;

/**
 * A commit with what its page shows around it, as on the web: above it the
 * commits it replies to, oldest first, or for a part of a series the earlier
 * parts; below it the later parts of its series, in order.
 */
final readonly class CommitThread
{
    /**
     * @param  list<Commit>  $ancestors
     * @param  list<Commit>  $series
     */
    public function __construct(public Commit $commit, public array $ancestors = [], public array $series = []) {}

    /**
     * @param  array<string, mixed>  $json
     */
    public static function fromArray(array $json): self
    {
        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $commit = is_array($data['commit'] ?? null) ? $data['commit'] : $data;

        $ancestors = $json['ancestors']
            ?? $data['ancestors']
            ?? $json['meta']['ancestors']
            ?? [];

        return new self(
            Commit::fromArray($commit),
            self::commits($ancestors),
            self::commits($json['series'] ?? null),
        );
    }

    /**
     * @return list<Commit>
     */
    private static function commits(mixed $items): array
    {
        return array_values(array_map(Commit::fromArray(...), array_filter(is_array($items) ? $items : [], is_array(...))));
    }
}
