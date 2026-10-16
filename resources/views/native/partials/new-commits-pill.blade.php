{{--
    DESIGN.md's new commits pill: Commit Blue, hanging under the tabs over the log, "↑ 3 new commits". The commits are
    already fetched, so a tap puts them on top with nothing to wait for. Laid over the list rather than in it, so
    nothing below moves when it shows.
--}}
<native:row class="absolute top-3 left-0 right-0 justify-center">
    <native:pressable ref="new-commits" @press="{{ $press }}" class="rounded-full bg-theme-primary shadow-md" a11y-label="Show {{ $label }}">
        <native:row class="px-4 py-[6] gap-[6] items-center">
            <native:icon :ios="\App\Icons\Lucide::ArrowUp->ios()" :android="\App\Icons\Lucide::ArrowUp->android()" :size="16" class="text-theme-on-primary" />
            <native:text font="medium" class="text-sm text-theme-on-primary">{{ $label }}</native:text>
        </native:row>
    </native:pressable>
</native:row>
