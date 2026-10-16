<?php

namespace App\Devnull\Data;

/**
 * One choice in a poll. Its votes and share are null while the poll keeps its results back.
 */
final readonly class PollOption
{
    public function __construct(
        public string $id,
        public string $label,
        public ?int $votesCount = null,
        public bool $voted = false,
        public ?int $percentage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            votesCount: is_int($data['votes_count'] ?? null) ? max(0, $data['votes_count']) : null,
            voted: ($data['voted'] ?? false) === true,
            percentage: is_int($data['percentage'] ?? null) ? max(0, min(100, $data['percentage'])) : null,
        );
    }

    /**
     * Its share of the votes in whole percent, as devnull rounded it.
     */
    public function share(): int
    {
        return $this->percentage ?? 0;
    }

    public function votesLabel(): string
    {
        $votes = $this->votesCount ?? 0;

        return $votes.' '.($votes === 1 ? 'vote' : 'votes');
    }
}
