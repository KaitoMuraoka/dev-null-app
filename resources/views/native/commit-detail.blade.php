@php($remaining = \App\Devnull\Data\Commit::MaxLength - mb_strlen($reply))
@php($replyLength = mb_strlen(trim($reply)))

<native:column class="w-full h-full bg-theme-background">
    @include('native.partials.error')

    <native:scroll-view class="w-full flex-1 bg-theme-surface">
        <native:column class="w-full bg-theme-surface">
            {{-- The ancestors and the commit on one rail, so someone replying to themselves shows their avatar once. --}}
            @foreach ($rows as $row)
                @include('native.partials.commit-row', [
                    'commit' => $row->commit,
                    'previousLane' => $row->previousLane,
                    'continuesRun' => $row->continuesRun,
                    'focused' => $row->commit->id === $commit?->id,
                ])
            @endforeach

            @if ($commit)
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
                    <native:column class="w-full px-4 py-4">
                        <native:text class="text-sm text-theme-ink-muted">No replies yet.</native:text>
                    </native:column>
                @endforelse

                @if ($repliesCursor !== null)
                    <native:column class="w-full px-4 py-3">
                        <native:button ref="more-replies" variant="ghost" size="sm" @press="loadMoreReplies">Show more replies</native:button>
                    </native:column>
                @endif

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
                        placeholder="Reply to {{ $commit->user->handle() }}"
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
