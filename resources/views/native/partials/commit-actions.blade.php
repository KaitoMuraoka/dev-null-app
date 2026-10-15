{{--
    The quiet row under a commit: its replies in Commit Blue, then its stars and whether you saved it.
    Stars and saves are read-only here, so they're printed rather than offered as buttons.
--}}
@php($withReplies ??= true)
@php($showsReplies = $withReplies && $commit->repliesCount > 0)
@php($showsStars = $commit->starsCount > 0 || $commit->starred)
@if ($showsReplies || $showsStars || $commit->isSaved === true)
    <native:row ref="actions-{{ $commit->id }}" class="w-full gap-3 items-center">
        @if ($showsReplies)
            <native:text class="text-sm text-theme-primary">{{ \App\Devnull\Display::count($commit->repliesCount, 'reply', 'replies') }}</native:text>
        @endif
        @if ($showsStars)
            <native:text ref="stars-{{ $commit->id }}" font="mono" class="text-[13px] {{ $commit->starred ? 'text-theme-primary' : 'text-theme-ink-muted' }}" a11y-label="{{ $commit->starred ? 'Starred, ' : '' }}{{ \App\Devnull\Display::count($commit->starsCount, 'star', 'stars') }}">{{ $commit->starred ? '★' : '☆' }} {{ $commit->starsCount }}</native:text>
        @endif
        @if ($commit->isSaved === true)
            <native:spacer />
            <native:icon ref="saved-{{ $commit->id }}" :ios="\App\Icons\Ios::BookmarkFill" :android="\App\Icons\Android::Bookmark" :size="14" class="text-theme-primary" a11y-label="Saved" />
        @endif
    </native:row>
@endif
