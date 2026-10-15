<native:column class="w-full h-full bg-theme-background">
    @include('native.partials.error')

    <native:list class="w-full flex-1 bg-theme-surface" on-refresh="load" on-end-reached="loadMore" :end-reached-buffer="5">
        @if ($profile)
            @php($user = $profile->user)
            <native:column class="w-full bg-theme-surface">
                <native:column ref="profile-header" class="w-full px-4 pt-6 pb-4 gap-4">
                    <native:row class="w-full items-start justify-between">
                        {{-- The avatar wears the author's lane as a ring, offset from the surface. --}}
                        <native:stack :width="92" :height="92" class="rounded-full bg-theme-lane-{{ $user->lane() }}">
                            <native:stack :width="88" :height="88" class="rounded-full bg-theme-surface">
                                @include('native.partials.avatar', ['user' => $user, 'size' => 80])
                            </native:stack>
                        </native:stack>

                        @unless ($isMe)
                            @if ($profile->isFollowing)
                                <native:button ref="follow" variant="secondary" size="sm" @press="toggleFollow" a11y-hint="Unfollows {{ $user->handle() }}">Unfollow</native:button>
                            @else
                                <native:button ref="follow" variant="primary" size="sm" @press="toggleFollow">Follow</native:button>
                            @endif
                        @endunless
                    </native:row>

                    <native:column class="w-full gap-1">
                        <native:text font="semibold" class="text-2xl tracking-tight text-theme-ink">{{ $user->name }}</native:text>
                        <native:row class="gap-2 items-center">
                            <native:column :width="8" :height="8" class="rounded-full bg-theme-lane-{{ $user->lane() }}" />
                            <native:text font="mono" class="text-[13px] text-theme-ink-muted">{{ $user->handle() }}</native:text>
                            @if ($profile->followsYou)
                                <native:column class="px-2 py-[2] rounded-full bg-theme-wash">
                                    <native:text class="text-xs text-theme-ink-muted">Follows you</native:text>
                                </native:column>
                            @endif
                        </native:row>
                    </native:column>

                    @if ($user->bio)
                        <native:text class="w-full text-[15px] leading-relaxed text-theme-ink">{{ $user->bio }}</native:text>
                    @endif

                    <native:row ref="counts" class="w-full gap-4">
                        @foreach ([[$profile->commitsCount, 'commit', 'commits'], [$profile->followingCount, 'following', 'following'], [$profile->followersCount, 'follower', 'followers']] as [$count, $singular, $plural])
                            <native:row class="gap-1 items-center">
                                <native:text font="mono-medium" class="text-[13px] text-theme-ink">{{ $count }}</native:text>
                                <native:text class="text-sm text-theme-ink-muted">{{ $count === 1 ? $singular : $plural }}</native:text>
                            </native:row>
                        @endforeach
                    </native:row>
                </native:column>
                <native:column class="w-full h-[1] bg-theme-hairline" />

                @include('native.partials.activity', ['activity' => $profile->activity, 'lane' => $user->lane()])
                @include('native.partials.streaks', ['streaks' => $profile->streaks, 'lane' => $user->lane()])
            </native:column>

            @include('native.partials.page-tabs', ['tabs' => [
                ['key' => 'commits', 'label' => 'Commits', 'active' => $tab === 'commits', 'press' => "showTab('commits')"],
                ['key' => 'replies', 'label' => 'Replies', 'active' => $tab === 'replies', 'press' => "showTab('replies')"],
            ]])

            @if ($commits === [] && $pinnedCommit === null && $error === null)
                @if ($tab === 'replies')
                    @include('native.partials.empty', ['command' => 'fatal: no replies yet', 'message' => $isMe ? 'Your replies show up here. Join a conversation from your log.' : "{$user->handle()} hasn't replied to anything yet."])
                @elseif ($isMe)
                    @include('native.partials.empty', ['command' => 'fatal: you do not have any commits yet', 'message' => 'Your commits show up here. Make your first one from your log.'])
                @else
                    @include('native.partials.empty', ['command' => 'fatal: no commits yet', 'message' => "{$user->handle()} hasn't committed anything yet."])
                @endif
            @endif

            @if ($pinnedCommit)
                {{-- The pinned commit sits above the rest, marked in mono as on the web, with no rail above it. --}}
                <native:row ref="pinned" class="w-full bg-theme-surface">
                    <native:column :width="64" />
                    <native:row class="flex-1 pt-3 gap-[6] items-center">
                        <native:icon :ios="\App\Icons\Lucide::Pin->ios()" :android="\App\Icons\Lucide::Pin->android()" :size="12" class="text-theme-ink-muted" />
                        <native:text font="mono" class="text-xs text-theme-ink-muted">Pinned</native:text>
                    </native:row>
                </native:row>
                @include('native.partials.commit-row', ['commit' => $pinnedCommit, 'previousLane' => null, 'continuesRun' => false])
            @endif

            @include('native.partials.feed', ['rows' => $rows])

            @if ($commits !== [] || $pinnedCommit)
                @if ($nextCursor !== null)
                    @include('native.partials.rail-loading')
                @elseif ($tab === 'replies')
                    @include('native.partials.rail-end', ['lane' => $user->lane(), 'message' => $isMe ? 'That was your first reply.' : "That's {$user->handle()}'s first reply."])
                @else
                    @include('native.partials.rail-end', ['lane' => $user->lane(), 'message' => $isMe ? 'That was your first commit. Everyone starts somewhere.' : "That's {$user->handle()}'s root commit."])
                @endif
            @endif
        @endif
    </native:list>
</native:column>
