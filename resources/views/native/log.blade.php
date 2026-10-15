<native:column class="w-full h-full bg-theme-background">
    @include('native.partials.page-tabs', ['tabs' => [
        ['key' => 'following', 'label' => 'Log', 'active' => $log === 'following', 'press' => "show('following')"],
        ['key' => 'everyone', 'label' => 'Everyone', 'active' => $log === 'everyone', 'press' => "show('everyone')"],
    ]])

    @include('native.partials.error')

    <native:list class="w-full flex-1 bg-theme-surface" on-refresh="load" on-end-reached="loadMore" :end-reached-buffer="5">
        @if ($viewer)
            @include('native.partials.composer-row', ['me' => $viewer])
        @endif

        @if ($loaded && $commits === [] && $error === null)
            @if ($log === 'following')
                @include('native.partials.empty', [
                    'command' => 'fatal: your log does not have any commits yet',
                    'message' => 'Your log shows your commits and commits from people you follow. Find someone worth following on Everyone.',
                ])
            @else
                @include('native.partials.empty', [
                    'command' => 'fatal: no commits yet',
                    'message' => "Nobody has committed anything. Be the first. It's 280 characters; you'll be fine.",
                ])
            @endif
        @endif

        @include('native.partials.feed', ['rows' => $rows])

        @if ($commits !== [])
            @if ($nextCursor !== null)
                @include('native.partials.rail-loading')
            @else
                @include('native.partials.rail-end', ['lane' => end($commits)->lane(), 'message' => "That's the root commit. There's nothing before it."])
            @endif
        @endif
    </native:list>
</native:column>
