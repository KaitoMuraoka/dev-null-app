{{--
    A reply under the focused commit: a compact row behind a Rail Gray guide line. A deleted reply that still has
    replies stays as a tombstone, and its own replies open in place below it, since a deleted commit has no page.
--}}
@php($nested ??= false)
@if ($reply->isDeleted())
    <native:row ref="reply-{{ $reply->id }}" class="w-full {{ $nested ? 'pl-8' : 'pl-4' }} pr-4 py-3 gap-3">
        <native:column :width="1" class="h-full bg-theme-rail" />
        @include('native.partials.tombstone-ring', ['size' => 28])
        <native:column class="flex-1 gap-1">
            <native:text class="text-sm text-theme-ink-muted"><native:text font="mono" class="text-xs">[deleted]</native:text> This commit was deleted.</native:text>
            @if ($reply->repliesCount > 0 && ! $nested)
                <native:pressable ref="tombstone-replies-{{ $reply->id }}" @press="toggleTombstone('{{ $reply->id }}')" a11y-label="{{ isset($tombstoneReplies[$reply->id]) ? 'Hide replies' : \App\Devnull\Display::count($reply->repliesCount, 'reply', 'replies') }}">
                    <native:text class="text-sm text-theme-primary">{{ isset($tombstoneReplies[$reply->id]) ? 'Hide replies' : \App\Devnull\Display::count($reply->repliesCount, 'reply', 'replies') }}</native:text>
                </native:pressable>
            @elseif ($reply->repliesCount > 0)
                <native:text class="text-sm text-theme-ink-muted">{{ \App\Devnull\Display::count($reply->repliesCount, 'reply', 'replies') }}</native:text>
            @endif
        </native:column>
    </native:row>
@else
    @php($commit = $reply)
    <native:pressable ref="reply-{{ $reply->id }}" @press="openCommit('{{ $reply->id }}')" class="w-full">
        <native:row class="w-full {{ $nested ? 'pl-8' : 'pl-4' }} pr-4 py-3 gap-3">
            <native:column :width="1" class="h-full bg-theme-rail" />
            <native:pressable @press="openProfile('{{ $reply->author->username }}')" a11y-label="{{ $reply->author->name }}'s profile">
                @include('native.partials.avatar', ['user' => $reply->author, 'size' => 28])
            </native:pressable>
            <native:column class="flex-1 gap-1">
                <native:row class="w-full gap-1 items-center">
                    <native:text font="semibold" class="text-sm text-theme-ink flex-shrink" :max-lines="1">{{ $reply->author->name }}</native:text>
                    <native:text class="text-[13px] text-theme-ink-muted">·</native:text>
                    <native:text font="mono" class="text-[13px] text-theme-ink-muted">{{ \App\Devnull\Display::relativeTime($reply->createdAt) }}</native:text>
                    @unless ($reply->isApproved())
                        <native:column class="ml-1 px-2 py-[2] rounded-full bg-theme-pending-soft">
                            <native:text font="mono" class="text-xs text-theme-pending">{{ $reply->status }}</native:text>
                        </native:column>
                    @endunless
                </native:row>
                @include('native.partials.commit-body')
                @include('native.partials.commit-media')
                @include('native.partials.commit-actions')
            </native:column>
        </native:row>
    </native:pressable>
@endif
