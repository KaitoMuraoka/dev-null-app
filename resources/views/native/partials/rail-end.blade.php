{{-- The end of history: a hollow root node with no tail and a dry line beside it. --}}
<native:row ref="root-commit" class="w-full bg-theme-surface">
    <native:column :width="44" />
    <native:column class="flex-1 pt-[24] pb-10 pr-4">
        <native:text class="text-sm text-theme-ink-muted">{{ $message }}</native:text>
    </native:column>

    @include('native.partials.rail', ['previous' => $lane ?? 'rail', 'lane' => $lane ?? 0, 'filled' => false])
</native:row>
