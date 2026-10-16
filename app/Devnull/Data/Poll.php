<?php

namespace App\Devnull\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A commit's poll, as the web's CommitPoll shows it. devnull keeps the counts
 * back until you've voted, wrote the poll, or it closed, so they can't sway a
 * vote. Until then $totalVotes is null and the options show without results.
 *
 * The clock only starts once the commit is approved, so a poll still in
 * moderation has no closing time.
 */
final readonly class Poll
{
    /**
     * @param  list<PollOption>  $options
     */
    public function __construct(
        public string $id,
        public array $options = [],
        public ?CarbonImmutable $closesAt = null,
        public bool $isClosed = false,
        public bool $hasVoted = false,
        public ?int $totalVotes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $options = array_values(array_map(PollOption::fromArray(...), array_filter(is_array($data['options'] ?? null) ? $data['options'] : [], is_array(...))));

        return new self(
            id: (string) ($data['id'] ?? ''),
            options: $options,
            closesAt: Commit::date($data['closes_at'] ?? null),
            isClosed: ($data['is_closed'] ?? false) === true,
            hasVoted: ($data['has_voted'] ?? false) === true,
            totalVotes: is_int($data['total_votes'] ?? null) ? max(0, $data['total_votes']) : null,
        );
    }

    /**
     * Whether devnull sent the counts, so the poll shows its results.
     */
    public function showsResults(): bool
    {
        return $this->totalVotes !== null;
    }

    public function hasExpired(?CarbonInterface $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $this->isClosed || ($this->closesAt !== null && $this->closesAt->lessThanOrEqualTo($now));
    }

    /**
     * The muted line under the options, worded as the web words it.
     */
    public function status(?CarbonInterface $now = null): string
    {
        $now ??= CarbonImmutable::now();

        return match (true) {
            $this->closesAt === null => 'Voting opens once approved',
            $this->hasExpired($now) => $this->showsResults() ? 'Final results' : 'Poll closed',
            default => self::timeLeft($this->closesAt, $now),
        };
    }

    /**
     * "4 votes · 3h left", the results' summary line.
     */
    public function summary(?CarbonInterface $now = null): string
    {
        $votes = $this->totalVotes ?? 0;

        return $votes.' '.($votes === 1 ? 'vote' : 'votes').' · '.$this->status($now);
    }

    /**
     * Whole days, then whole hours, then minutes, never less than one: the web's timeLeft().
     */
    private static function timeLeft(CarbonInterface $closesAt, CarbonInterface $now): string
    {
        $minutes = (int) ceil(($closesAt->getTimestamp() - $now->getTimestamp()) / 60);

        return match (true) {
            $minutes >= 1440 => intdiv($minutes, 1440).'d left',
            $minutes >= 60 => intdiv($minutes, 60).'h left',
            default => max($minutes, 1).'m left',
        };
    }
}
