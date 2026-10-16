{{--
    A numbered step's heading: the number in mono on a ring in lane 0, the colour of the logo's outlined slash, then a
    short title. The step's content goes under it, indented 36pt to line up with the title.
--}}
<native:row class="w-full gap-3 items-center">
    <native:stack :width="24" :height="24" center class="rounded-full bg-theme-lane-0">
        <native:stack :width="20" :height="20" center class="rounded-full bg-theme-background">
            <native:text font="mono-medium" class="text-xs text-theme-ink">{{ $number }}</native:text>
        </native:stack>
    </native:stack>
    <native:text font="semibold" class="flex-1 text-[15px] tracking-tight text-theme-ink">{{ $title }}</native:text>
</native:row>
