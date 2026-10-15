<?php

namespace App\NativeComponents;

use App\Devnull\Data\Commit;
use App\NativeComponents\Concerns\TalksToDevnull;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Dialog;

/**
 * A new commit: 280 characters, staged on the graph until it's committed.
 */
class Compose extends NativeComponent
{
    use TalksToDevnull;

    public string $body = '';

    public function navTitle(): string
    {
        return 'New commit';
    }

    public function remaining(): int
    {
        return Commit::MaxLength - mb_strlen($this->body);
    }

    public function canCommit(): bool
    {
        $length = mb_strlen(trim($this->body));

        return $length >= Commit::MinLength && $length <= Commit::MaxLength;
    }

    public function commit(): void
    {
        if (! $this->canCommit()) {
            return;
        }

        $commit = $this->attempt(fn ($devnull) => $devnull->createCommit(trim($this->body)));

        if ($commit === null) {
            return;
        }

        $this->body = '';

        Dialog::toast($commit->isPending()
            ? "[log {$commit->shortHash()}] pending: only you can see it until it's approved."
            : "[log {$commit->shortHash()}] {$commit->body}");

        $this->back();
    }

    public function render(): View
    {
        return view('native.compose', [
            'me' => $this->me(),
            'remaining' => $this->remaining(),
            'canCommit' => $this->canCommit(),
        ]);
    }
}
