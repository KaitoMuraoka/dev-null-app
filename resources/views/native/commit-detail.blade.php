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
                their avatar once. As on the web, the commit's replies hang under it on the thread's dashed lines, with
                the rail ending in a dot above them. A part of a series with more parts after it has its replies right
                under it, beside the rail running on, and the later parts follow. A deleted reply's own replies open in
                place, one level in, since a deleted commit has no page.
            --}}
            @foreach ($rows as $row)
                @php($isFocused = $row->commit->id === $commit?->id)
                @php($hasThread = $isFocused && $replies !== [])
                @include('native.partials.commit-row', [
                    'commit' => $row->commit,
                    'previousLane' => $row->previousLane,
                    'continuesRun' => $row->continuesRun,
                    'continuesNext' => $isFocused ? ! $loop->last : null,
                    'threadBelow' => $hasThread,
                    'focused' => $isFocused,
                    'seriesExpanded' => true,
                    'withTail' => ! $loop->last,
                ])
                @if ($hasThread)
                    @php($railLane = $loop->last ? null : $commit->lane())
                    @foreach ($replies as $index => $threadReply)
                        @php($nested = $tombstoneReplies[$threadReply->id] ?? [])
                        @php($hasNext = ! $loop->last || $repliesCursor !== null)
                        @include('native.partials.thread-reply', [
                            'reply' => $threadReply,
                            'lane' => $commit->lane(),
                            'previousLane' => $index === 0 ? null : $replies[$index - 1]->lane(),
                            'hasNext' => $hasNext,
                            'railLane' => $railLane,
                            'tombstones' => true,
                            'thread' => $commit,
                            'more' => $loop->last && $nested === [] && $repliesCursor !== null ? 'loadMoreReplies' : null,
                            'last' => $loop->last && $nested === [],
                        ])
                        @foreach ($nested as $nestedIndex => $nestedReply)
                            @include('native.partials.thread-reply', [
                                'reply' => $nestedReply,
                                'lane' => \App\Devnull\Lane::Rail,
                                'previousLane' => $nestedIndex === 0 ? null : $nested[$nestedIndex - 1]->lane(),
                                'hasNext' => ! $loop->last,
                                'railLane' => $railLane,
                                'indent' => 32,
                                'spine' => $hasNext ? $threadReply->lane() : null,
                                'thread' => $commit,
                                'more' => $loop->last && $loop->parent->last && $repliesCursor !== null ? 'loadMoreReplies' : null,
                                'last' => $loop->last && $loop->parent->last,
                            ])
                        @endforeach
                    @endforeach
                @endif
            @endforeach

            @if ($commit && $replies === [] && $series === [])
                @if ($repliesLoaded)
                    <native:column class="w-full px-4 py-4">
                        <native:text class="text-sm text-theme-ink-muted">No replies yet.</native:text>
                    </native:column>
                @elseif ($error === null)
                    <native:row ref="loading-replies" class="w-full px-4 py-4 gap-2 items-center" a11y-label="Loading replies">
                        <native:activity-indicator size="small" />
                    </native:row>
                @endif
            @endif

            {{--
                A pushed screen runs under its bottom bar, so the page clears the reply bar itself (see
                Display::replyBarClearance): the last reply and its actions end above the bar, keyboard up or down.
            --}}
            @if ($commit)
                <native:column ref="reply-bar-clearance" class="w-full h-[{{ 24 + \App\Devnull\Display::replyBarClearance($reply) }}]" />
            @endif
        </native:column>
    </native:scroll-view>

    @if ($commit)
        {{--
            The bar is painted from its first child's background: native:bottom-bar takes no classes of its own, so
            the surface and the hairline above it belong to this column, or the replies show through.
        --}}
        <native:bottom-bar>
            <native:column ref="reply-bar" class="w-full bg-theme-surface">
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
                        :max-length="\App\Devnull\Data\Commit::MaxLength"
                    />
                    @include('native.partials.body-counter', ['body' => $reply, 'ref' => 'reply-budget'])
                    <native:button ref="send-reply" size="sm" :disabled="$replyLength < 3 || $remaining < 0" @press="sendReply">Reply</native:button>
                </native:row>
            </native:column>
        </native:bottom-bar>
    @endif
</native:column>
