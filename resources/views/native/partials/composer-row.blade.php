{{--
    The top of the log, like the web's composer: your avatar staged on the rail in a dashed ring with a dashed Rail
    Gray tail, beside "What did you ship?". Tapping it opens Compose.
--}}
<native:pressable ref="composer" @press="compose" class="w-full bg-theme-surface" :press-opacity="0.7" a11y-label="New commit">
    <native:row class="w-full">
        <native:column :width="64" />
        <native:column class="flex-1">
            <native:row class="w-full h-[72] pr-4 pb-[8] items-center">
                <native:text class="text-[17px] text-theme-ink-muted">What did you ship?</native:text>
            </native:row>
            <native:column class="w-full h-[1] bg-theme-hairline" />
        </native:column>

        @include('native.partials.rail', ['lane' => $me->lane(), 'user' => $me, 'node' => 'staged', 'tail' => 'staged', 'tailDashes' => 2])
    </native:row>
</native:pressable>
