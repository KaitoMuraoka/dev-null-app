<native:column class="w-full h-full bg-theme-background">
    @include('native.partials.page-tabs', ['tabs' => array_map(fn (string $key): array => [
        'key' => $key,
        'label' => \App\NativeComponents\Log::Labels[$key],
        'active' => $log === $key,
        'press' => "show('{$key}')",
    ], \App\NativeComponents\Log::Logs)])

    @include('native.partials.error')
    @include('native.partials.refresh-failed')

    <native:column class="w-full flex-1">
    {{-- Keyed so a new key puts the log back at the top, as the nav bar's compose button and the pill do. --}}
    <native:list native:key="log-{{ $listKey }}" ref="log" class="w-full flex-1 bg-theme-surface" on-refresh="load" on-end-reached="loadMore" :end-reached-buffer="5">
        @if ($viewer)
            @include('native.partials.composer', ['me' => $viewer])
        @endif

        @if (! $loaded && $error === null)
            @foreach (range(1, 3) as $skeleton)
                @include('native.partials.rail-loading', ['ref' => 'loading-'.$skeleton, 'label' => 'Loading commits'])
            @endforeach
        @endif

        @if ($loaded && $isEmpty && $error === null)
            @if ($log === 'following')
                @include('native.partials.following-empty', ['suggestions' => $suggestions])
            @else
                @include('native.partials.empty', [
                    'command' => 'fatal: no commits yet',
                    'message' => "Nobody has committed anything. Be the first. It's just plain text; you'll be fine.",
                ])
            @endif
        @endif

        @include('native.partials.feed', ['rows' => $rows])

        @if ($lastCommit)
            @if ($nextCursor !== null)
                @include('native.partials.rail-loading')
            @else
                @include('native.partials.rail-end', ['lane' => $lastCommit->lane(), 'message' => "That's the root commit. There's nothing before it."])
            @endif
        @endif
    </native:list>

    @if ($newCommits !== [])
        @include('native.partials.new-commits-pill', ['label' => $newCommitsLabel, 'press' => 'showNewCommits'])
    @endif
    </native:column>
</native:column>
