@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:column class="w-full h-full bg-theme-background">
    @include('native.partials.error')

    <native:list class="w-full flex-1 bg-theme-surface" on-refresh="load" on-end-reached="loadMore" :end-reached-buffer="5">
        @if ($loaded && $notifications === [] && $error === null)
            @include('native.partials.empty', ['command' => '$ cat /dev/notifications', 'message' => 'Nothing here yet. Follows, stars, replies, mentions, and moderation updates for your commits will show up here.'])
        @endif

        @foreach ($notifications as $notification)
            @php($icon = match ($notification->type) {
                'followed' => [Ios::PersonBadgePlus, Android::PersonAdd],
                'mentioned' => [Ios::At, Android::AlternateEmail],
                'starred' => [Ios::StarFill, Android::Star],
                'approved' => [Ios::Checkmark, Android::Check],
                'rejected' => [Ios::Xmark, Android::Close],
                default => [Ios::ArrowshapeTurnUpLeft, Android::Reply],
            })
            {{-- No press-* feedback: on iOS it holds any drag that starts on the row, and these rows cover the whole list (see commit-row). --}}
            <native:pressable ref="notification-{{ $notification->id }}" @press="open('{{ $notification->id }}')" class="w-full {{ $notification->isUnread ? 'bg-theme-primary-soft' : 'bg-theme-surface' }}">
                <native:row class="w-full px-4 py-3 gap-3">
                    <native:column :width="24" class="items-center pt-1">
                        {{-- A person's notification wears their lane; a moderator's stays muted. --}}
                        <native:icon :ios="$icon[0]" :android="$icon[1]" :size="20" class="{{ $notification->isModeration() ? 'text-theme-ink-muted' : 'text-theme-lane-'.$notification->actor->lane() }}" />
                    </native:column>

                    <native:column class="flex-1 gap-2">
                        <native:row class="w-full items-center">
                            @if ($notification->isModeration())
                                <native:text font="semibold" class="text-sm text-theme-ink">Moderation update</native:text>
                            @else
                                @include('native.partials.stacked-avatars', ['id' => $notification->id, 'actors' => $notification->actors])
                            @endif
                            <native:spacer />
                            @if ($notification->isUnread)
                                <native:column :width="8" :height="8" class="rounded-full bg-theme-primary" a11y-label="Unread" />
                            @endif
                        </native:row>

                        <native:text class="w-full text-[15px] text-theme-ink">
                            @if ($notification->isModeration())
                                <native:text>{{ $notification->sentence() }}</native:text>
                            @else
                                {{-- "Ada and 2 others starred your commit", as on the web: the newest person's name, then the rest in plain text. --}}
                                <native:text font="semibold">{{ $notification->actor->name }}</native:text>
                                <native:text :text="$notification->others().' '.$notification->sentence()" />
                            @endif
                            <native:text class="text-theme-ink-muted"> · </native:text>
                            <native:text font="mono" class="text-[13px] text-theme-ink-muted">{{ \App\Devnull\Display::relativeTime($notification->createdAt) }}</native:text>
                        </native:text>

                        @if ($notification->opensCommit() && $notification->body)
                            <native:text class="w-full text-[15px] leading-relaxed text-theme-ink" :max-lines="3">{{ $notification->body }}</native:text>
                        @endif
                        @if ($notification->isReply() && $notification->parentBody)
                            <native:text class="w-full text-sm text-theme-ink-muted" :max-lines="1">↳ {{ $notification->parentBody }}</native:text>
                        @endif

                        @if ($notification->isRejection())
                            <native:column ref="rejection-{{ $notification->id }}" class="w-full gap-1">
                                @if ($notification->reason)
                                    <native:text class="w-full text-sm text-theme-ink whitespace-pre-line"><native:text font="semibold">Reason: </native:text>{{ $notification->reason }}</native:text>
                                @endif
                                <native:text class="w-full text-sm text-theme-ink-muted">Only you can see this update and your rejected commit.</native:text>
                            </native:column>
                        @endif
                    </native:column>
                </native:row>
                <native:column class="w-full h-[1] bg-theme-hairline" />
            </native:pressable>
        @endforeach

        @if ($nextCursor !== null)
            <native:row ref="loading-more" class="w-full py-4 justify-center">
                <native:activity-indicator size="small" />
            </native:row>
        @endif
    </native:list>
</native:column>
