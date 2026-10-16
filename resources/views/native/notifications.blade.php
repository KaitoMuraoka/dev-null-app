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

    {{--
        The filters as pages you swipe between, as on the Log: the one you're on and one either side, which show the
        first few groups kept for them. Keyed so the banners above coming and going don't make it a new pager.
    --}}
    <native:pager native:key="notifications-pager" ref="notifications-pager" horizontal class="w-full flex-1 bg-theme-surface" :count="count($types)" :page="$pageIndex" :from="$pagesFrom" :to="$pagesTo" on-page-change="showPage" a11y-label="Notifications">
        @foreach ($pages as $page)
            @include('native.partials.notifications-page', ['page' => $page])
        @endforeach
    </native:pager>
</native:column>
