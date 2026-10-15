{{-- The inside of a commit row, for commit-row to wrap in a link or, for the focused commit, a plain row. --}}
@php($author = $commit->user)
<native:column :width="64" />

<native:column class="flex-1">
    <native:column class="w-full min-h-[64] py-3 pr-4 gap-1">
        <native:row class="w-full gap-[6] items-center">
            <native:text font="semibold" class="text-[15px] text-theme-ink flex-shrink" :max-lines="1">{{ $author->name }}</native:text>
            <native:text font="mono" class="text-[13px] text-theme-ink-muted flex-shrink" :max-lines="1">{{ $author->handle() }}</native:text>
            <native:text class="text-[13px] text-theme-ink-muted">·</native:text>
            <native:text font="mono" class="text-[13px] text-theme-ink-muted flex-shrink-0">{{ \App\Devnull\Display::relativeTime($commit->createdAt) }}</native:text>
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
            <native:pressable ref="replying-to-{{ $commit->id }}" @press="openCommit('{{ $commit->parent->id }}')" a11y-label="Replying to {{ $commit->parent->user->handle() }}">
                <native:row class="gap-1 items-center">
                    <native:icon :ios="\App\Icons\Lucide::CornerDownRight->ios()" :android="\App\Icons\Lucide::CornerDownRight->android()" :size="12" class="text-theme-ink-muted" />
                    <native:text font="mono" class="text-xs text-theme-ink-muted">replying to {{ $commit->parent->user->handle() }}</native:text>
                </native:row>
            </native:pressable>
        @endif

        @include('native.partials.commit-body', ['size' => $focused ? 'text-[17px]' : 'text-[15px]'])
        @include('native.partials.commit-media')

        @if ($focused)
            <native:text font="mono" class="mt-1 text-[13px] text-theme-ink-muted">{{ \App\Devnull\Display::gitDate($commit->createdAt) }} · {{ $commit->shortHash() }}</native:text>
        @endif

        @include('native.partials.commit-actions', ['withReply' => ! $focused, 'withReplies' => ! $focused])
    </native:column>
    <native:column class="w-full h-[1] bg-theme-hairline" />
</native:column>

@include('native.partials.rail', [
    'previous' => $previousLane,
    'lane' => $commit->lane(),
    'tail' => $focused ? null : $commit->lane(),
    'node' => $continuesRun ? 'dot' : 'avatar',
    'user' => $author,
    'press' => "openProfile('{$author->username}')",
])
