{{--
    A reply opened in place under a commit in a feed, like a reply in the web's thread: a 24pt avatar, the name and
    time, the body and its actions, with the commit's lane running on down the rail beside it. A deleted reply keeps
    its place as a muted line. The last reply closes the commit's row with its hairline.
--}}
@if ($reply->isDeleted())
    <native:row ref="thread-reply-{{ $reply->id }}" class="w-full bg-theme-surface">
        <native:column :width="64" />
        <native:column class="flex-1">
            <native:text class="w-full pr-4 pb-3 text-sm text-theme-ink-muted"><native:text font="mono" class="text-xs">[deleted]</native:text> This commit was deleted.</native:text>
            @if ($last)
                <native:column class="w-full h-[1] bg-theme-hairline" />
            @endif
        </native:column>
        @include('native.partials.rail', ['previous' => $lane])
    </native:row>
@else
    @php($commit = $reply)
    <native:pressable ref="thread-reply-{{ $reply->id }}" @press="openCommit('{{ $reply->id }}')" class="w-full bg-theme-surface" :press-opacity="0.7">
        <native:row class="w-full">
            <native:column :width="64" />
            <native:column class="flex-1">
                <native:row class="w-full pr-4 pb-3 gap-[10]">
                    <native:pressable @press="openProfile('{{ $reply->author->username }}')" a11y-label="{{ $reply->author->name }}'s profile">
                        @include('native.partials.avatar', ['user' => $reply->author, 'size' => 24])
                    </native:pressable>
                    <native:column class="flex-1 gap-1">
                        <native:row class="w-full gap-[6] items-center">
                            <native:text font="semibold" class="text-sm text-theme-ink flex-shrink" :max-lines="1">{{ $reply->author->name }}</native:text>
                            <native:text class="text-[13px] text-theme-ink-muted">·</native:text>
                            <native:text font="mono" class="text-[13px] text-theme-ink-muted flex-shrink-0">{{ \App\Devnull\Display::relativeTime($reply->createdAt) }}</native:text>
                            @unless ($reply->isApproved())
                                <native:column class="ml-1 px-2 py-[2] rounded-full bg-theme-pending-soft flex-shrink-0">
                                    <native:text font="mono" class="text-xs text-theme-pending">{{ $reply->status }}</native:text>
                                </native:column>
                            @endunless
                        </native:row>
                        @include('native.partials.commit-body', ['size' => 'text-sm'])
                        @include('native.partials.commit-media')
                        @include('native.partials.commit-actions', ['expandable' => false])
                    </native:column>
                </native:row>
                @if ($last)
                    <native:column class="w-full h-[1] bg-theme-hairline" />
                @endif
            </native:column>
            @include('native.partials.rail', ['previous' => $lane])
        </native:row>
    </native:pressable>
@endif
