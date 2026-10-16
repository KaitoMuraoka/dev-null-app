<native:column class="w-full h-full bg-theme-background">
    {{--
        The web's filters, as page tabs that scroll sideways on a phone. EDGE gives PHP no way to scroll a list, so the
        row can't bring the current filter into view as the web does; it stays where you scrolled it to tap one.
    --}}
    <native:column class="w-full bg-theme-surface">
        <native:scroll-view ref="filters" horizontal :shows-indicators="false" class="w-full">
            <native:row class="px-1 pt-[2]">
                @foreach ($types as $key => [$label])
                    <native:pressable ref="filter-{{ $key }}" @press="show('{{ $key }}')" class="px-3" a11y-label="{{ $label }}{{ $type === $key ? ', selected' : '' }}">
                        <native:column class="items-center pt-3 gap-[9]">
                            <native:text font="medium" class="text-[15px] {{ $type === $key ? 'text-theme-ink' : 'text-theme-ink-muted' }}">{{ $label }}</native:text>
                            <native:column class="w-full h-[3] {{ $type === $key ? 'bg-theme-primary' : 'bg-transparent' }}" />
                        </native:column>
                    </native:pressable>
                @endforeach
            </native:row>
        </native:scroll-view>
        <native:column class="w-full h-[1] bg-theme-hairline" />
    </native:column>

    @include('native.partials.error')
    @include('native.partials.refresh-failed')

    <native:list native:key="notifications-{{ $type }}" class="w-full flex-1 bg-theme-surface" on-refresh="load" on-end-reached="loadMore" :end-reached-buffer="5">
        @if (! $loaded && $error === null)
            <native:column ref="loading-notifications" class="w-full items-center py-16 bg-theme-surface" a11y-label="Loading notifications">
                <native:activity-indicator />
            </native:column>
        @endif

        @if ($loaded && $notifications === [] && $error === null)
            <native:column ref="empty-state" class="w-full items-center px-8 py-16 gap-3 bg-theme-surface">
                <native:text font="mono" class="text-[13px] text-center text-theme-ink-muted">{{ $type === 'all' ? '$ cat /dev/notifications' : '$ cat /dev/notifications | grep '.$type }}</native:text>
                <native:text class="text-[15px] text-center leading-relaxed text-theme-ink max-w-[320]">{{ $types[$type][1] }}</native:text>
                @if ($type !== 'all')
                    <native:pressable ref="show-all" @press="show('all')" a11y-label="Show all notifications">
                        <native:text class="text-[15px] text-theme-primary">Show all notifications</native:text>
                    </native:pressable>
                @endif
            </native:column>
        @endif

        @foreach ($notifications as $index => $notification)
            @php($previous = $notifications[$index - 1] ?? null)
            @if ($notification->id === $lastVisitBefore)
                @include('native.partials.last-visit-marker', ['lane' => \App\Devnull\Lane::Rail, 'label' => 'Notifications above are new since your last visit'])
            @endif
            @if ($notification->createdAt && ($previous?->createdAt === null || \App\Devnull\Display::dayKey($previous->createdAt) !== \App\Devnull\Display::dayKey($notification->createdAt)))
                @include('native.partials.day-marker', ['label' => \App\Devnull\Display::dayLabel($notification->createdAt), 'lane' => $previous === null ? null : \App\Devnull\Lane::Rail])
            @endif
            @include('native.partials.notification', ['notification' => $notification, 'hasPrevious' => $previous !== null, 'isNew' => $notification->isNewSince($readAt)])
        @endforeach

        @if ($notifications !== [])
            @if ($nextCursor !== null)
                <native:row ref="loading-more" class="w-full py-4 justify-center">
                    <native:activity-indicator size="small" />
                </native:row>
            @else
                <native:row ref="oldest-notification" class="w-full bg-theme-surface">
                    <native:column :width="64" />
                    <native:column class="flex-1 pt-[21] pb-10 pr-4">
                        <native:text class="text-sm text-theme-ink-muted">That's the oldest notification. There's nothing before it.</native:text>
                    </native:column>
                    @include('native.partials.rail', ['previous' => \App\Devnull\Lane::Rail, 'lane' => \App\Devnull\Lane::Rail, 'node' => 'root'])
                </native:row>
            @endif
        @endif
    </native:list>
</native:column>
