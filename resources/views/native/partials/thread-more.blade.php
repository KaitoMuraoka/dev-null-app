{{--
    "Show more" at the foot of a feed's thread, as the web's: a muted rail dot where the next avatar would be, on a
    short dashed link from the last reply ($previousLane), and the commit's row closing under it with its hairline.
--}}
@php($railLane ??= null)
@php($press ??= "showMoreReplies('{$commit->id}')")
@php($segment = fn (int|string $lane): string => $lane === \App\Devnull\Lane::Rail ? 'bg-theme-rail' : 'bg-theme-lane-'.$lane)
@php($avatarLine = 76)
<native:pressable ref="more-replies-{{ $commit->id }}" @press="{{ $press }}" class="w-full bg-theme-surface" a11y-label="Show more replies">
    <native:row class="w-full">
        <native:column :width="64" />
        <native:column class="flex-1">
            <native:row class="w-full pt-[6] pb-3 pr-4 gap-2 items-center">
                <native:stack :width="24" :height="24" center>
                    <native:stack :width="18" :height="18" center class="rounded-full bg-theme-ink-muted">
                        <native:stack :width="14" :height="14" center class="rounded-full bg-theme-surface">
                            <native:column :width="8" :height="8" class="rounded-full bg-theme-ink-muted" />
                        </native:stack>
                    </native:stack>
                </native:stack>
                <native:text class="text-[13px] text-theme-ink-muted">Show more</native:text>
            </native:row>
            <native:column class="w-full h-[1] bg-theme-hairline" />
        </native:column>
        @if ($railLane !== null)
            @include('native.partials.rail', ['previous' => $railLane])
        @endif
        @include('native.partials.dashed-line', ['position' => 'top-0 h-[12]', 'left' => $avatarLine - 0.5, 'colour' => $segment($previousLane), 'dashes' => 2])
    </native:row>
</native:pressable>
