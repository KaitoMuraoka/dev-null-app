<?php

namespace App\NativeComponents;

use App\Devnull\Data\Commit;
use App\Devnull\Display;
use App\Devnull\Timeline;
use App\Icons\Android;
use App\Icons\Ios;
use App\NativeComponents\Concerns\OpensInTab;
use App\NativeComponents\Concerns\ShowsCommits;
use App\NativeComponents\Concerns\TalksToDevnull;
use Closure;
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
 * Deleted ancestors and replies show as tombstones.
 */
#[Lazy]
class CommitDetail extends NativeComponent
{
    use OpensInTab;
    use ShowsCommits;
    use TalksToDevnull;

    public const string DeleteAlert = 'delete-commit';

    public string $id = '';

    public ?Commit $commit = null;

    /** @var list<Commit> */
    public array $ancestors = [];

    /** @var list<Commit> */
    public array $replies = [];

    public ?string $repliesCursor = null;

    /**
     * The replies of deleted replies that were opened in place, by the deleted reply's id.
     *
     * @var array<string, list<Commit>>
     */
    public array $tombstoneReplies = [];

    public string $reply = '';

    /**
     * Opened from a Reply action, so the reply composer takes the focus.
     */
    public bool $focusReply = false;

    public function mount(): void
    {
        $this->focusReply = $this->data('reply', false) === true;
        $this->load();
    }

    /**
     * A reply opened from here may have been deleted, or replied to, in the meantime.
     */
    public function onResume(): void
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

        foreach (array_keys($this->tombstoneReplies) as $id) {
            $this->loadTombstoneReplies((string) $id);
        }
    }

    /**
     * A deleted reply has no page of its own, so its replies open in place below it.
     */
    public function toggleTombstone(string $id): void
    {
        if (array_key_exists($id, $this->tombstoneReplies)) {
            unset($this->tombstoneReplies[$id]);

            return;
        }

        $this->loadTombstoneReplies($id);
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
        Dialog::alert('Delete this commit?', "There's no undo.", [
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
        $this->openInTab('/commits/'.$id);
    }

    public function openProfile(string $username): void
    {
        $this->openInTab('/users/'.$username);
    }

    protected function updateShownCommits(Closure $update): void
    {
        $this->commit = $this->commit === null ? null : $update($this->commit);
        $this->ancestors = array_map($update, $this->ancestors);
        $this->replies = array_map($update, $this->replies);
        $this->tombstoneReplies = array_map(fn (array $replies): array => array_map($update, $replies), $this->tombstoneReplies);
    }

    /**
     * @return iterable<Commit>
     */
    protected function shownCommits(): iterable
    {
        return [...($this->commit === null ? [] : [$this->commit]), ...$this->ancestors, ...$this->replies, ...array_merge([], ...array_values($this->tombstoneReplies))];
    }

    private function loadTombstoneReplies(string $id): void
    {
        $page = $this->attempt(fn ($devnull) => $devnull->replies($id));

        if ($page !== null) {
            $this->tombstoneReplies[$id] = $page->items;
        }
    }

    public function isOwn(): bool
    {
        return $this->commit !== null && $this->commit->user->username === $this->me()?->username;
    }

    public function render(): View
    {
        return view('native.commit-detail', [
            'viewer' => $this->me(),
            'rows' => $this->commit === null ? [] : Timeline::thread([...$this->ancestors, $this->commit]),
        ]);
    }
}
