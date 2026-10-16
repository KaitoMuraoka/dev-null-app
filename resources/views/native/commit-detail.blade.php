@php($remaining = \App\Devnull\Data\Commit::MaxLength - mb_strlen($reply))
@php($replyLength = mb_strlen(trim($reply)))

<native:column class="w-full h-full bg-theme-background">
    @include('native.partials.error')
    @include('native.partials.refresh-failed')

    <native:scroll-view class="w-full flex-1 bg-theme-surface">
        <native:column class="w-full bg-theme-surface">
            @if (! $commit && $error === null)
                <native:column ref="loading-commit" class="w-full items-center py-16" a11y-label="Loading commit">
                    <native:activity-indicator />
                </native:column>
            @endif

            {{--
                The ancestors, the commit and the rest of its series on one rail, so someone replying to themselves shows
                their avatar once. As on the web, a part of a series with more parts after it has its replies right under
                it, opened in place beside the rail, and the later parts follow.
            --}}
            @php($repliesInThread = $series !== [] && $replies !== [])
            @foreach ($rows as $row)
                @php($isFocused = $row->commit->id === $commit?->id)
                @include('native.partials.commit-row', [
                    'commit' => $row->commit,
                    'previousLane' => $row->previousLane,
                    'continuesRun' => $row->continuesRun,
                    'focused' => $isFocused,
                    'seriesExpanded' => true,
                    'withTail' => ! $loop->last,
                    'hairline' => ! ($isFocused && $repliesInThread),
                ])
                @if ($isFocused && $repliesInThread)
                    @foreach ($replies as $index => $threadReply)
                        @include('native.partials.thread-reply', [
                            'reply' => $threadReply,
                            'lane' => $commit->lane(),
                            'previousLane' => $index === 0 ? null : $replies[$index - 1]->lane(),
                            'hasNext' => ! $loop->last || $repliesCursor !== null,
                            'railLane' => $commit->lane(),
                            'last' => $loop->last && $repliesCursor === null,
                        ])
                    @endforeach
                    @if ($repliesCursor !== null)
                        <native:row class="w-full bg-theme-surface">
                            <native:column :width="64" />
                            <native:column class="flex-1">
                                <native:column class="w-full pr-4 pb-3">
                                    <native:button ref="more-replies" variant="ghost" size="sm" @press="loadMoreReplies">Show more replies</native:button>
                                </native:column>
                                <native:column class="w-full h-[1] bg-theme-hairline" />
                            </native:column>
                            @include('native.partials.rail', ['previous' => $commit->lane()])
                        </native:row>
                    @endif
                @endif
            @endforeach

            @if ($commit && $series === [])
                @if ($replies !== [])
                    <native:column class="w-full px-4 pt-3 pb-1">
                        <native:text font="mono" class="text-[13px] text-theme-ink-muted">{{ \App\Devnull\Display::count(max($commit->repliesCount, count($replies)), 'reply', 'replies') }}</native:text>
                    </native:column>
                @endif

                @forelse ($replies as $replyCommit)
                    @include('native.partials.reply', ['reply' => $replyCommit])
                    @foreach ($tombstoneReplies[$replyCommit->id] ?? [] as $nestedReply)
                        @include('native.partials.reply', ['reply' => $nestedReply, 'nested' => true])
                    @endforeach
                @empty
                    @if ($repliesLoaded)
                        <native:column class="w-full px-4 py-4">
                            <native:text class="text-sm text-theme-ink-muted">No replies yet.</native:text>
                        </native:column>
                    @elseif ($error === null)
                        <native:row ref="loading-replies" class="w-full px-4 py-4 gap-2 items-center" a11y-label="Loading replies">
                            <native:activity-indicator size="small" />
                        </native:row>
                    @endif
                @endforelse

                @if ($repliesCursor !== null)
                    <native:column class="w-full px-4 py-3">
                        <native:button ref="more-replies" variant="ghost" size="sm" @press="loadMoreReplies">Show more replies</native:button>
                    </native:column>
                @endif
            @endif

            @if ($commit)
                <native:column class="w-full h-[24]" />
            @endif
        </native:column>
    </native:scroll-view>

    @if ($commit)
        <native:bottom-bar class="w-full bg-theme-surface">
            <native:column class="w-full">
                <native:column class="w-full h-[1] bg-theme-hairline" />
                <native:row class="w-full px-4 py-2 gap-3 items-center">
                    <native:bare-text-input
                        ref="reply-input"
                        class="flex-1 py-2 text-theme-ink"
                        placeholder="Reply to {{ $commit->author->handle() }}"
                        native:model.debounce.150ms="reply"
                        :autofocus="$focusReply"
                        multiline
                        :max-lines="5"
                        :max-length="280"
                    />
                    <native:text ref="reply-budget" font="mono" class="text-[13px] {{ $remaining <= 0 ? 'text-theme-destructive' : 'text-theme-ink-muted' }}">{{ $remaining }}</native:text>
                    <native:button ref="send-reply" size="sm" :disabled="$replyLength < 3 || $remaining < 0" @press="sendReply">Reply</native:button>
                </native:row>
            </native:column>
        </native:bottom-bar>
    @endif
</native:column>
