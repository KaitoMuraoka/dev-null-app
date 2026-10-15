{{-- The rail keeps running while the next page loads. --}}
<native:row ref="loading-more" class="w-full bg-theme-surface">
    <native:column :width="44" />
    <native:row class="flex-1 py-4 pr-4 gap-2 items-center">
        <native:activity-indicator size="small" />
        <native:text font="mono" class="text-[13px] text-theme-ink-muted">git log --skip={{ $count }}</native:text>
    </native:row>

    @include('native.partials.rail', ['previous' => 'rail', 'tail' => 'rail', 'node' => false])
</native:row>
