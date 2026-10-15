{{--
    The action row under a commit, in the web's order: Reply, the star and its count, "› N replies" in Commit Blue,
    copy link, then the bookmark. Reply and the replies link open the commit; copy link opens the share sheet.
    The API can't star or save yet, so the star and the bookmark only show their state. Like the web, both are left
    off commits you can't react to: someone else's that isn't approved.
--}}
@php($withReply ??= true)
@php($withReplies ??= true)
@php($viewer ??= app(\App\Devnull\Account::class)->user())
@php($canReact = $commit->isApproved() || $commit->user->id === $viewer?->id)
@php($starLabel = ($commit->starred ? 'Starred' : 'Not starred').($commit->starsCount > 0 ? ', '.\App\Devnull\Display::count($commit->starsCount, 'star', 'stars') : ''))
<native:row ref="actions-{{ $commit->id }}" class="w-full -ml-2 items-center">
    @if ($withReply)
        <native:pressable ref="reply-to-{{ $commit->id }}" @press="openCommit('{{ $commit->id }}')" class="px-2 py-1">
            <native:row class="gap-[6] items-center">
                <native:icon :ios="\App\Icons\Lucide::MessageSquareReply->ios()" :android="\App\Icons\Lucide::MessageSquareReply->android()" :size="16" class="text-theme-ink-muted" />
                <native:text class="text-[13px] text-theme-ink-muted">Reply</native:text>
            </native:row>
        </native:pressable>
    @endif

    @if ($canReact)
        <native:row ref="stars-{{ $commit->id }}" class="px-2 py-1 gap-1 items-center" a11y-label="{{ $starLabel }}">
            @if ($commit->starred || \Native\Mobile\Platform::isAndroid())
                <native:icon :ios="\App\Icons\Lucide::Star->ios($commit->starred)" :android="\App\Icons\Lucide::Star->android($commit->starred)" :size="16" class="{{ $commit->starred ? 'text-theme-primary' : 'text-theme-ink-muted' }}" />
            @else
                {{-- iOS has no outline star by name (see App\Icons\Lucide), and the system font's ☆ is the same drawing. --}}
                <native:text class="text-[18px] text-theme-ink-muted">☆</native:text>
            @endif
            @if ($commit->starsCount > 0)
                <native:text font="mono" class="text-[13px] {{ $commit->starred ? 'text-theme-primary' : 'text-theme-ink-muted' }}">{{ $commit->starsCount }}</native:text>
            @endif
        </native:row>
    @endif

    @if ($withReplies && $commit->repliesCount > 0)
        <native:pressable ref="replies-{{ $commit->id }}" @press="openCommit('{{ $commit->id }}')" class="px-2 py-1">
            <native:row class="gap-1 items-center">
                <native:icon :ios="\App\Icons\Lucide::ChevronRight->ios()" :android="\App\Icons\Lucide::ChevronRight->android()" :size="14" class="text-theme-primary" />
                <native:text class="text-[13px] text-theme-primary">{{ \App\Devnull\Display::count($commit->repliesCount, 'reply', 'replies') }}</native:text>
            </native:row>
        </native:pressable>
    @endif

    <native:pressable ref="copy-link-{{ $commit->id }}" @press="shareCommit('{{ $commit->id }}')" class="px-2 py-1" a11y-label="Copy link">
        <native:icon :ios="\App\Icons\Lucide::Link->ios()" :android="\App\Icons\Lucide::Link->android()" :size="16" class="text-theme-ink-muted" />
    </native:pressable>

    @if ($canReact && $commit->isSaved !== null)
        <native:column ref="saved-{{ $commit->id }}" class="px-2 py-1" a11y-label="{{ $commit->isSaved ? 'Saved' : 'Not saved' }}">
            <native:icon :ios="\App\Icons\Lucide::Bookmark->ios($commit->isSaved)" :android="\App\Icons\Lucide::Bookmark->android($commit->isSaved)" :size="16" class="{{ $commit->isSaved ? 'text-theme-primary' : 'text-theme-ink-muted' }}" />
        </native:column>
    @endif
</native:row>
