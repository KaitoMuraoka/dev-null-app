<?php

namespace App\Devnull\Data;

/**
 * A commit with the ancestors above it, oldest first.
 */
final readonly class CommitThread
{
    /**
     * @param  list<Commit>  $ancestors
     */
    public function __construct(public Commit $commit, public array $ancestors = []) {}

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
            array_values(array_map(Commit::fromArray(...), array_filter(is_array($ancestors) ? $ancestors : [], is_array(...)))),
        );
    }
}
