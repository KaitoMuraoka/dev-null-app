{{--
    A reply in the thread under a commit in a feed, like a reply in the web's thread: a 24pt avatar lined up with the
    commit's text, the name and time, the body and its actions. The thread hangs off the rail in dashes of each
    author's lane, as upstream's #121 draws it: the first reply on an elbow from the rail ($lane is the commit's), each
    later one on a short link from the reply above ($previousLane), and a line on down from its avatar when another
    reply, or "Show more", follows ($hasNext). $railLane runs the commit's own rail on past, solid, when its author's
    run goes on below the thread. A deleted reply keeps its place with a dashed ring. The $last closes the commit's row
    with its hairline.
--}}
@php($previousLane ??= null)
@php($railLane ??= null)
@php($segment = fn (int|string $lane): string => $lane === \App\Devnull\Lane::Rail ? 'bg-theme-rail' : 'bg-theme-lane-'.$lane)
@php($avatarLine = 76)
@if ($reply->isDeleted())
    <native:row ref="thread-reply-{{ $reply->id }}" class="w-full bg-theme-surface">
        <native:column :width="64" />
        <native:column class="flex-1">
            <native:row class="w-full pr-4 pt-[10] gap-2 {{ $last ? 'pb-3' : 'pb-1' }} items-center">
                <native:column :width="24" :height="24" class="rounded-full">
                    @include('native.partials.dashed-ring', ['size' => 24, 'colour' => 'bg-theme-rail'])
                </native:column>
                <native:text class="flex-1 text-sm text-theme-ink-muted"><native:text font="mono" class="text-xs">[deleted]</native:text> This commit was deleted.</native:text>
            </native:row>
            @if ($last)
                <native:column class="w-full h-[1] bg-theme-hairline" />
            @endif
        </native:column>
        @include('native.partials.thread-links')
    </native:row>
@else
    @php($commit = $reply)
    <native:pressable ref="thread-reply-{{ $reply->id }}" @press="openCommit('{{ $reply->id }}')" class="w-full bg-theme-surface">
        <native:row class="w-full">
            <native:column :width="64" />
            <native:column class="flex-1">
                <native:row class="w-full pr-4 pt-[10] gap-2 {{ $last ? 'pb-3' : '' }}">
                    <native:pressable @press="openProfile('{{ $reply->author->username }}')" a11y-label="{{ $reply->author->name }}'s profile">
                        @include('native.partials.avatar', ['user' => $reply->author, 'size' => 24])
                    </native:pressable>
                    <native:column class="flex-1 gap-1">
                        <native:row class="w-full min-h-[24] gap-[6] items-center">
                            <native:text font="semibold" class="text-sm text-theme-ink flex-shrink" :max-lines="1">{{ $reply->author->name }}</native:text>
                            <native:text class="text-[13px] text-theme-ink-muted">·</native:text>
                            <native:text font="mono" class="text-xs text-theme-ink-muted flex-shrink-0">{{ \App\Devnull\Display::relativeTime($reply->createdAt) }}</native:text>
                            @unless ($reply->isApproved())
                                <native:column class="ml-1 px-2 py-[2] rounded-full bg-theme-pending-soft flex-shrink-0">
                                    <native:text font="mono" class="text-xs text-theme-pending">{{ $reply->status }}</native:text>
                                </native:column>
                            @endunless
                        </native:row>
                        @include('native.partials.commit-body', ['size' => 'text-[15px]'])
                        @include('native.partials.commit-media')
                        @include('native.partials.commit-actions', ['expandable' => false, 'withQuote' => false])
                    </native:column>
                </native:row>
                @if ($last)
                    <native:column class="w-full h-[1] bg-theme-hairline" />
                @endif
            </native:column>
            @include('native.partials.thread-links')
        </native:row>
    </native:pressable>
@endif
