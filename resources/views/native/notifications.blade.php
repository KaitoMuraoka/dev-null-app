@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:column class="w-full h-full bg-theme-background">
    @include('native.partials.error')

    <native:list class="w-full flex-1 bg-theme-surface" on-refresh="load" on-end-reached="loadMore" :end-reached-buffer="5">
        @if ($loaded && $notifications === [] && $error === null)
            @include('native.partials.empty', ['command' => '$ cat /dev/notifications', 'message' => 'Nothing here yet. Follows and replies to your commits will show up here.'])
        @endif

        @foreach ($notifications as $notification)
            <native:pressable ref="notification-{{ $notification->id }}" @press="open('{{ $notification->id }}')" class="w-full {{ $notification->isUnread ? 'bg-theme-primary-soft' : 'bg-theme-surface' }}" :press-opacity="0.7">
                <native:row class="w-full px-4 py-3 gap-3">
                    <native:column :width="24" class="items-center pt-1">
                        @if ($notification->isReply())
                            <native:icon :ios="Ios::ArrowshapeTurnUpLeft" :android="Android::Reply" :size="20" class="text-theme-lane-{{ $notification->actor->lane() }}" />
                        @else
                            <native:icon :ios="Ios::PersonBadgePlus" :android="Android::PersonAdd" :size="20" class="text-theme-lane-{{ $notification->actor->lane() }}" />
                        @endif
                    </native:column>

                    <native:column class="flex-1 gap-2">
                        <native:row class="w-full items-center">
                            @include('native.partials.avatar', ['user' => $notification->actor, 'size' => 32])
                            <native:spacer />
                            @if ($notification->isUnread)
                                <native:column :width="8" :height="8" class="rounded-full bg-theme-primary" a11y-label="Unread" />
                            @endif
                        </native:row>

                        <native:text class="w-full text-[15px] text-theme-ink">
                            <native:text font="semibold">{{ $notification->actor->name }}</native:text>
                            {{ $notification->isReply() ? 'replied to your commit' : 'followed you' }}
                            <native:text class="text-theme-ink-muted"> · </native:text>
                            <native:text font="mono" class="text-[13px] text-theme-ink-muted">{{ \App\Devnull\Display::relativeTime($notification->createdAt) }}</native:text>
                        </native:text>

                        @if ($notification->isReply())
                            @if ($notification->body)
                                <native:text class="w-full text-[15px] leading-relaxed text-theme-ink whitespace-pre-line">{{ $notification->body }}</native:text>
                            @endif
                            @if ($notification->parentBody)
                                <native:text class="w-full text-sm text-theme-ink-muted" :max-lines="1">↳ {{ $notification->parentBody }}</native:text>
                            @endif
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
