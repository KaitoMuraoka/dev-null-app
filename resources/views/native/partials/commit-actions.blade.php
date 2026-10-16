{{--
    The action row under a commit, in the web's order: Reply, the star and its count, "› N replies" in Commit Blue,
    copy link, then the bookmark. Reply opens the commit with its reply composer focused. In a feed ($expandable)
    the replies link opens the thread in place, as on the web; elsewhere it opens the commit. The star and the
    bookmark toggle. Like the web, both are left off commits you can't react to: someone else's that isn't approved.
--}}
@php($withReply ??= true)
@php($withReplies ??= true)
@php($expandable ??= isset($threads))
@php($isOpen = $expandable && array_key_exists($commit->id, $threads))
@php($repliesPress = $expandable ? "toggleReplies('{$commit->id}')" : "openCommit('{$commit->id}')")
@php($viewer ??= app(\App\Devnull\Account::class)->user())
@php($canReact = $commit->isApproved() || $commit->author->id === $viewer?->id)
@php($starLabel = ($commit->starred ? 'Unstar commit' : 'Star commit').($commit->starsCount > 0 ? ', '.\App\Devnull\Display::count($commit->starsCount, 'star', 'stars') : ''))
<native:row ref="actions-{{ $commit->id }}" class="w-full -ml-2 items-center">
    @if ($withReply)
        <native:pressable ref="reply-to-{{ $commit->id }}" @press="replyTo('{{ $commit->id }}')" class="px-2 py-1">
            <native:row class="gap-[6] items-center">
                <native:icon :ios="\App\Icons\Lucide::MessageSquareReply->ios()" :android="\App\Icons\Lucide::MessageSquareReply->android()" :size="16" class="text-theme-ink-muted" />
                <native:text class="text-[13px] text-theme-ink-muted">Reply</native:text>
            </native:row>
        </native:pressable>
    @endif

    @if ($canReact)
        <native:pressable ref="star-{{ $commit->id }}" @press="toggleStar('{{ $commit->id }}')" class="px-2 py-1" a11y-label="{{ $starLabel }}">
            <native:row class="gap-1 items-center">
                <native:icon :ios="\App\Icons\Lucide::Star->ios($commit->starred)" :android="\App\Icons\Lucide::Star->android($commit->starred)" :size="16" class="{{ $commit->starred ? 'text-theme-primary' : 'text-theme-ink-muted' }}" />
                @if ($commit->starsCount > 0)
                    <native:text font="mono" class="text-[13px] {{ $commit->starred ? 'text-theme-primary' : 'text-theme-ink-muted' }}">{{ $commit->starsCount }}</native:text>
                @endif
            </native:row>
        </native:pressable>
    @endif

    @if ($withReplies && $commit->repliesCount > 0)
        <native:pressable ref="replies-{{ $commit->id }}" @press="{{ $repliesPress }}" class="px-2 py-1">
            <native:row class="gap-1 items-center">
                @if ($isOpen)
                    <native:icon :ios="\App\Icons\Lucide::ChevronDown->ios()" :android="\App\Icons\Lucide::ChevronDown->android()" :size="14" class="text-theme-primary" />
                    <native:text class="text-[13px] text-theme-primary">Hide replies</native:text>
                @else
                    <native:icon :ios="\App\Icons\Lucide::ChevronRight->ios()" :android="\App\Icons\Lucide::ChevronRight->android()" :size="14" class="text-theme-primary" />
                    <native:text class="text-[13px] text-theme-primary">{{ \App\Devnull\Display::count($commit->repliesCount, 'reply', 'replies') }}</native:text>
                @endif
            </native:row>
        </native:pressable>
    @endif

    <native:pressable ref="copy-link-{{ $commit->id }}" @press="shareCommit('{{ $commit->id }}')" class="px-2 py-1" a11y-label="Copy link">
        <native:icon :ios="\App\Icons\Lucide::Link->ios()" :android="\App\Icons\Lucide::Link->android()" :size="16" class="text-theme-ink-muted" />
    </native:pressable>

    @if ($canReact && $commit->isSaved !== null)
        <native:pressable ref="bookmark-{{ $commit->id }}" @press="toggleBookmark('{{ $commit->id }}')" class="px-2 py-1" a11y-label="{{ $commit->isSaved ? 'Unsave commit' : 'Save commit' }}">
            <native:icon :ios="\App\Icons\Lucide::Bookmark->ios($commit->isSaved)" :android="\App\Icons\Lucide::Bookmark->android($commit->isSaved)" :size="16" class="{{ $commit->isSaved ? 'text-theme-primary' : 'text-theme-ink-muted' }}" />
        </native:pressable>
    @endif
</native:row>
