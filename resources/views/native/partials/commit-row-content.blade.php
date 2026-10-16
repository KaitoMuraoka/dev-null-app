{{--
    The inside of a commit row, for commit-row to wrap in a link or, for the focused commit, a plain row.
    A part of a series wears its place in it ("1/3"). Shown on its own ($seriesExpanded false), a part with more
    after it links to them, as the web's "2 more commits in this series" does. $withTail runs the rail on below.
--}}
@php($author = $commit->author)
@php($hairline ??= true)
@php($seriesExpanded ??= false)
@php($withTail ??= ! $focused)
@php($laterParts = $seriesExpanded ? 0 : $commit->laterSeriesParts())
<native:column :width="64" />

<native:column class="flex-1">
    <native:column class="w-full min-h-[64] py-3 pr-4 gap-1">
        <native:row class="w-full gap-[6] items-center">
            <native:text font="semibold" class="text-[15px] text-theme-ink flex-shrink" :max-lines="1">{{ $author->name }}</native:text>
            <native:text font="mono" class="text-[13px] text-theme-ink-muted flex-shrink" :max-lines="1">{{ $author->handle() }}</native:text>
            <native:text class="text-[13px] text-theme-ink-muted">·</native:text>
            <native:text font="mono" class="text-[13px] text-theme-ink-muted flex-shrink-0">{{ \App\Devnull\Display::relativeTime($commit->createdAt) }}</native:text>
            @if ($commit->isSeriesPart())
                @include('native.partials.series-position')
            @endif
            @unless ($commit->isApproved())
                <native:column class="ml-1 px-2 py-[2] rounded-full bg-theme-pending-soft flex-shrink-0">
                    <native:text font="mono" class="text-xs text-theme-pending">{{ $commit->status }}</native:text>
                </native:column>
            @endunless
        </native:row>

        @if ($commit->parent?->isDeleted())
            <native:row ref="replying-to-{{ $commit->id }}" class="gap-1 items-center">
                <native:icon :ios="\App\Icons\Lucide::CornerDownRight->ios()" :android="\App\Icons\Lucide::CornerDownRight->android()" :size="12" class="text-theme-ink-muted" />
                <native:text font="mono" class="text-xs text-theme-ink-muted">replying to [deleted]</native:text>
            </native:row>
        @elseif ($commit->parent)
            <native:pressable ref="replying-to-{{ $commit->id }}" @press="openCommit('{{ $commit->parent->id }}')" a11y-label="Replying to {{ $commit->parent->author->handle() }}">
                <native:row class="gap-1 items-center">
                    <native:icon :ios="\App\Icons\Lucide::CornerDownRight->ios()" :android="\App\Icons\Lucide::CornerDownRight->android()" :size="12" class="text-theme-ink-muted" />
                    <native:text font="mono" class="text-xs text-theme-ink-muted">replying to {{ $commit->parent->author->handle() }}</native:text>
                </native:row>
            </native:pressable>
        @endif

        @include('native.partials.commit-body', ['size' => $focused ? 'text-[17px]' : 'text-[15px]'])
        @include('native.partials.commit-media')

        @if ($laterParts > 0)
            <native:pressable ref="series-continues-{{ $commit->id }}" @press="openCommit('{{ $commit->id }}')" class="mt-1" a11y-label="{{ $laterParts }} more {{ $laterParts === 1 ? 'commit' : 'commits' }} in this series">
                <native:row class="gap-[6] items-center">
                    <native:icon :ios="\App\Icons\Lucide::ListOrdered->ios()" :android="\App\Icons\Lucide::ListOrdered->android()" :size="14" class="text-theme-primary" />
                    <native:text font="mono" class="text-xs text-theme-primary">{{ $laterParts }} more {{ $laterParts === 1 ? 'commit' : 'commits' }} in this series</native:text>
                </native:row>
            </native:pressable>
        @endif

        @if ($focused)
            <native:text font="mono" class="mt-1 text-[13px] text-theme-ink-muted">{{ \App\Devnull\Display::gitDate($commit->createdAt) }} · {{ $commit->shortHash }}</native:text>
        @endif

        @include('native.partials.commit-actions', ['withReply' => ! $focused, 'withReplies' => ! $focused])
    </native:column>
    @if ($hairline)
        <native:column class="w-full h-[1] bg-theme-hairline" />
    @endif
</native:column>

@include('native.partials.rail', [
    'previous' => $previousLane,
    'lane' => $commit->lane(),
    'tail' => $withTail ? $commit->lane() : null,
    'node' => $continuesRun ? 'dot' : 'avatar',
    'user' => $author,
    'press' => "openProfile('{$author->username}')",
])
