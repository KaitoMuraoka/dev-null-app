<?php

namespace App\NativeComponents;

use App\Devnull\Data\Commit;
use App\Icons\Android;
use App\Icons\Ios;
use App\NativeComponents\Concerns\TalksToDevnull;
use Illuminate\View\View;
use Native\Mobile\Attributes\Lazy;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\Layouts\Builders\NavBarOptions;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Transition;

/**
 * The log: commits from you and the people you follow, or from everyone.
 */
#[Lazy]
class Log extends NativeComponent
{
    use TalksToDevnull;

    public const array Logs = ['following', 'everyone'];

    public string $log = 'following';

    /** @var list<Commit> */
    public array $commits = [];

    public ?string $nextCursor = null;

    public bool $loaded = false;

    public function mount(): void
    {
        $this->load();
    }

    public function onResume(): void
    {
        $this->load();
    }

    public function navTitle(): string
    {
        return 'Log';
    }

    public function navigationOptions(): ?NavBarOptions
    {
        return NavBarOptions::make()->action(
            NavAction::make('compose')
                ->icon(ios: Ios::SquareAndPencil, android: Android::Edit)
                ->a11yLabel('New commit')
                ->press('compose'),
        );
    }

    public function show(string $log): void
    {
        if (! in_array($log, self::Logs, true)) {
            return;
        }

        $this->log = $log;
        $this->commits = [];
        $this->nextCursor = null;
        $this->load();
    }

    public function load(): void
    {
        $page = $this->attempt(fn ($devnull) => $devnull->log($this->log));

        if ($page !== null) {
            $this->commits = $page->items;
            $this->nextCursor = $page->nextCursor;
        }

        $this->loaded = true;
    }

    public function loadMore(): void
    {
        if ($this->nextCursor === null) {
            return;
        }

        $page = $this->attempt(fn ($devnull) => $devnull->log($this->log, $this->nextCursor));

        if ($page !== null) {
            $this->commits = [...$this->commits, ...$page->items];
            $this->nextCursor = $page->nextCursor;
        }
    }

    public function compose(): void
    {
        $this->navigate('/compose')->transition(Transition::SlideFromBottom);
    }

    public function openCommit(string $id): void
    {
        $this->navigate('/commits/'.$id);
    }

    public function openProfile(string $username): void
    {
        $this->navigate('/users/'.$username);
    }

    public function render(): View
    {
        return view('native.log');
    }
}
