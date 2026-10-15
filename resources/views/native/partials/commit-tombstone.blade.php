{{--
    A deleted commit that still has replies keeps its place on the rail, the way the web draws it: a hollow Rail Gray
    node and segment, an empty ring where the avatar was, and a muted line in git's voice. It isn't a link.
--}}
@php($previousLane ??= null)
<native:row ref="tombstone-{{ $commit->id }}" class="w-full bg-theme-surface">
    <native:column :width="44" />

    <native:column class="flex-1">
        <native:row class="w-full gap-3 py-3 pr-4 items-center">
            @include('native.partials.tombstone-ring', ['size' => 40])
            <native:text class="flex-1 text-[15px] text-theme-ink-muted"><native:text font="mono" class="text-[13px]">[deleted]</native:text> This commit was deleted.</native:text>
        </native:row>
        <native:column class="w-full h-[1] bg-theme-hairline" />
    </native:column>

    @include('native.partials.rail', ['previous' => $previousLane, 'lane' => \App\Devnull\Lane::Rail, 'tail' => \App\Devnull\Lane::Rail, 'filled' => false])
</native:row>
