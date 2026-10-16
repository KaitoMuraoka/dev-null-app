<native:column class="w-full h-full bg-theme-background">
    @include('native.partials.page-tabs', ['tabs' => array_map(fn (string $key): array => [
        'key' => $key,
        'label' => \App\NativeComponents\Log::Labels[$key],
        'active' => $log === $key,
        'press' => "show('{$key}')",
    ], \App\NativeComponents\Log::Logs)])

    @include('native.partials.error')
    @include('native.partials.refresh-failed')
    @include('native.partials.commit-sheet')

    {{--
        The logs as pages you swipe between. The pager keeps where it is across renders, and only moves for a tab tap,
        when $pageIndex is one it didn't report itself. Keyed so the banners above coming and going don't make it a
        new pager, with new lists.
    --}}
    <native:pager native:key="log-pager" ref="log-pager" horizontal class="w-full flex-1 bg-theme-surface" :count="count($pages)" :page="$pageIndex" on-page-change="showPage" a11y-label="Logs">
        @foreach ($pages as $page)
            @include('native.partials.log-page', ['page' => $page])
        @endforeach
    </native:pager>
</native:column>
