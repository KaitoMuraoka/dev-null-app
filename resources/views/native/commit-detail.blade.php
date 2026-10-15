@php($remaining = \App\Devnull\Data\Commit::MaxLength - mb_strlen($reply))
@php($replyLength = mb_strlen(trim($reply)))

<native:column class="w-full h-full bg-theme-background">
    @include('native.partials.error')

    <native:scroll-view class="w-full flex-1 bg-theme-surface">
        <native:column class="w-full bg-theme-surface">
            @foreach ($ancestors as $ancestor)
                @include('native.partials.commit-row', ['commit' => $ancestor, 'previousLane' => $loop->first ? null : $ancestors[$loop->index - 1]->lane()])
            @endforeach

            @if ($commit)
                <native:row ref="focused-commit" class="w-full">
                    <native:column :width="44" />

                    <native:column class="flex-1 py-3 pr-4 gap-3">
                        <native:pressable @press="openProfile('{{ $commit->user->username }}')" class="w-full" a11y-label="{{ $commit->user->name }}'s profile">
                            <native:row class="w-full gap-3 items-center">
                                @include('native.partials.avatar', ['user' => $commit->user, 'size' => 40])
                                <native:column class="flex-1">
                                    <native:text font="semibold" class="text-[15px] text-theme-ink" :max-lines="1">{{ $commit->user->name }}</native:text>
                                    <native:text font="mono" class="text-[13px] text-theme-ink-muted" :max-lines="1">{{ $commit->user->handle() }}</native:text>
                                </native:column>
                            </native:row>
                        </native:pressable>

                        <native:column class="w-full gap-1">
                            @include('native.partials.commit-body', ['size' => 'text-[17px]'])
                            @include('native.partials.commit-media')
                        </native:column>

                        <native:row class="w-full gap-2 items-center">
                            <native:text font="mono" class="text-[13px] text-theme-ink-muted">{{ \App\Devnull\Display::gitDate($commit->createdAt) }} · {{ $commit->shortHash() }}</native:text>
                            @unless ($commit->isApproved())
                                <native:column class="px-2 py-[2] rounded-full bg-theme-pending-soft">
                                    <native:text font="mono" class="text-xs text-theme-pending">{{ $commit->status }}</native:text>
                                </native:column>
                            @endunless
                        </native:row>

                        @include('native.partials.commit-actions', ['withReplies' => false])
                    </native:column>

                    @include('native.partials.rail', ['previous' => $ancestors === [] ? null : end($ancestors)->lane(), 'lane' => $commit->lane()])
                </native:row>

                <native:column class="w-full h-[1] bg-theme-hairline" />

                @if ($replies !== [])
                    <native:column class="w-full px-4 pt-3 pb-1">
                        <native:text font="mono" class="text-[13px] text-theme-ink-muted">{{ \App\Devnull\Display::count(max($commit->repliesCount, count($replies)), 'reply', 'replies') }}</native:text>
                    </native:column>
                @endif

                @forelse ($replies as $replyCommit)
                    <native:pressable ref="reply-{{ $replyCommit->id }}" @press="openCommit('{{ $replyCommit->id }}')" class="w-full" :press-opacity="0.7">
                        <native:row class="w-full pl-4 pr-4 py-3 gap-3">
                            <native:column :width="1" class="h-full bg-theme-rail" />
                            <native:pressable @press="openProfile('{{ $replyCommit->user->username }}')" a11y-label="{{ $replyCommit->user->name }}'s profile">
                                @include('native.partials.avatar', ['user' => $replyCommit->user, 'size' => 28])
                            </native:pressable>
                            <native:column class="flex-1 gap-1">
                                <native:row class="w-full gap-1 items-center">
                                    <native:text font="semibold" class="text-sm text-theme-ink flex-shrink" :max-lines="1">{{ $replyCommit->user->name }}</native:text>
                                    <native:text class="text-[13px] text-theme-ink-muted">·</native:text>
                                    <native:text font="mono" class="text-[13px] text-theme-ink-muted">{{ \App\Devnull\Display::relativeTime($replyCommit->createdAt) }}</native:text>
                                    @unless ($replyCommit->isApproved())
                                        <native:column class="ml-1 px-2 py-[2] rounded-full bg-theme-pending-soft">
                                            <native:text font="mono" class="text-xs text-theme-pending">{{ $replyCommit->status }}</native:text>
                                        </native:column>
                                    @endunless
                                </native:row>
                                @include('native.partials.commit-body', ['commit' => $replyCommit])
                                @include('native.partials.commit-media', ['commit' => $replyCommit])
                                @include('native.partials.commit-actions', ['commit' => $replyCommit])
                            </native:column>
                        </native:row>
                    </native:pressable>
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
