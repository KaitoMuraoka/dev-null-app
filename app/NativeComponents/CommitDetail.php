<?php

namespace App\NativeComponents;

use App\Devnull\Data\Commit;
use App\Devnull\Display;
use App\Icons\Android;
use App\Icons\Ios;
use App\NativeComponents\Concerns\TalksToDevnull;
use Illuminate\View\View;
use Native\Mobile\Attributes\Lazy;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\Layouts\Builders\NavBarOptions;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;

/**
 * One commit: its ancestors above, its replies below, and a reply composer.
 */
#[Lazy]
class CommitDetail extends NativeComponent
{
    use TalksToDevnull;

    public const string DeleteAlert = 'delete-commit';

    public string $id = '';

    public ?Commit $commit = null;

    /** @var list<Commit> */
    public array $ancestors = [];

    /** @var list<Commit> */
    public array $replies = [];

    public ?string $repliesCursor = null;

    public string $reply = '';

    public function mount(): void
    {
        $this->load();
    }

    public function navTitle(): string
    {
        return 'Commit';
    }

    public function navigationOptions(): ?NavBarOptions
    {
        $options = NavBarOptions::make()->subtitle(Display::shortHash($this->id));

        if ($this->isOwn()) {
            $options = $options->action(
                NavAction::make('delete')
                    ->icon(ios: Ios::Trash, android: Android::Delete)
                    ->a11yLabel('Delete commit')
                    ->destructive()
                    ->press('confirmDelete'),
            );
        }

        return $options;
    }

    public function load(): void
    {
        $thread = $this->attempt(fn ($devnull) => $devnull->commit($this->id));

        if ($thread === null) {
            return;
        }

        $this->commit = $thread->commit;
        $this->ancestors = $thread->ancestors;

        $replies = $this->attempt(fn ($devnull) => $devnull->replies($this->id));

        if ($replies !== null) {
            $this->replies = $replies->items;
            $this->repliesCursor = $replies->nextCursor;
        }
    }

    public function loadMoreReplies(): void
    {
        if ($this->repliesCursor === null) {
            return;
        }

        $page = $this->attempt(fn ($devnull) => $devnull->replies($this->id, $this->repliesCursor));

        if ($page !== null) {
            $this->replies = [...$this->replies, ...$page->items];
            $this->repliesCursor = $page->nextCursor;
        }
    }

    public function sendReply(): void
    {
        $body = trim($this->reply);

        if (mb_strlen($body) < Commit::MinLength || mb_strlen($body) > Commit::MaxLength) {
            return;
        }

        $reply = $this->attempt(fn ($devnull) => $devnull->createCommit($body, $this->id));

        if ($reply === null) {
            return;
        }

        $this->reply = '';
        $this->replies = [...$this->replies, $reply];

        Dialog::toast($reply->isPending()
            ? "[reply {$reply->shortHash()}] pending: only you can see it until it's approved."
            : "[reply {$reply->shortHash()}] {$reply->body}", 'short');
    }

    public function confirmDelete(): void
    {
        Dialog::alert('Delete this commit?', "There's no undo. Its replies go with it.", [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Delete', 'style' => 'destructive'],
        ])->id(self::DeleteAlert);
    }

    #[On(ButtonPressed::class)]
    public function onAlertButton(string $label = '', ?string $id = null): void
    {
        if ($id === self::DeleteAlert && $label === 'Delete') {
            $this->delete();
        }
    }

    public function delete(): void
    {
        if (! $this->isOwn()) {
            return;
        }

        $deleted = $this->attempt(function ($devnull): bool {
            $devnull->deleteCommit($this->id);

            return true;
        });

        if ($deleted === true) {
            Dialog::toast('rm '.Display::shortHash($this->id).' → /dev/null', 'short');
            $this->back();
        }
    }

    public function openCommit(string $id): void
    {
        if ($id !== $this->id) {
            $this->navigate('/commits/'.$id);
        }
    }

    public function openProfile(string $username): void
    {
        $this->navigate('/users/'.$username);
    }

    public function isOwn(): bool
    {
        return $this->commit !== null && $this->commit->user->username === $this->me()?->username;
    }

    public function render(): View
    {
        return view('native.commit-detail');
    }
}
