<?php

namespace App\Devnull\Data;

/**
 * A profile's achievements as the web's shelf shows them: the Unix rank and
 * its level, the XP towards the next rank, and the badges, unlocked or not.
 */
final readonly class Achievements
{
    /**
     * @param  list<array{rank: string, level: int, min_xp: int}>  $ranks
     * @param  list<Badge>  $badges
     */
    public function __construct(
        public int $level = 0,
        public string $rank = '',
        public int $xp = 0,
        public ?int $nextLevelXp = null,
        public int $levelProgress = 0,
        public array $ranks = [],
        public array $badges = [],
    ) {}

    /**
     * Reads the profile's `achievements`, or null when devnull sent none.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data) || ! is_int($data['level'] ?? null) || ! is_string($data['rank'] ?? null) || $data['rank'] === '') {
            return null;
        }

        $ranks = [];

        foreach (is_array($data['ranks'] ?? null) ? $data['ranks'] : [] as $rank) {
            if (is_array($rank) && is_string($rank['rank'] ?? null) && is_int($rank['level'] ?? null) && is_int($rank['min_xp'] ?? null)) {
                $ranks[] = ['rank' => $rank['rank'], 'level' => $rank['level'], 'min_xp' => $rank['min_xp']];
            }
        }

        return new self(
            level: $data['level'],
            rank: $data['rank'],
            xp: is_int($data['xp'] ?? null) ? max(0, $data['xp']) : 0,
            nextLevelXp: is_int($data['next_level_xp'] ?? null) ? $data['next_level_xp'] : null,
            levelProgress: is_int($data['level_progress'] ?? null) ? max(0, min(100, $data['level_progress'])) : 0,
            ranks: $ranks,
            badges: array_values(array_map(Badge::fromArray(...), array_filter(is_array($data['badges'] ?? null) ? $data['badges'] : [], is_array(...)))),
        );
    }

    /**
     * "Lv. 3 · operator", as the badge beside the handle reads.
     */
    public function label(): string
    {
        return "Lv. {$this->level} · {$this->rank}";
    }

    public function unlockedCount(): int
    {
        return count(array_filter($this->badges, fn (Badge $badge): bool => $badge->unlocked));
    }

    /**
     * "120 / 250 XP", or just the XP at the top rank.
     */
    public function xpLabel(): string
    {
        return $this->nextLevelXp === null ? "{$this->xp} XP" : "{$this->xp} / {$this->nextLevelXp} XP";
    }

    /**
     * "130 XP to operator", or the web's line for the top rank.
     */
    public function nextRankLabel(): string
    {
        foreach ($this->ranks as $rank) {
            if ($rank['level'] === $this->level + 1) {
                return ($rank['min_xp'] - $this->xp).' XP to '.$rank['rank'];
            }
        }

        return 'Maximum rank (UID 0)';
    }
}
