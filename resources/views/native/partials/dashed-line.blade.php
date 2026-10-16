{{--
    A 1pt vertical line in dashes, standing in for the web's `border-l border-dashed`, since EDGE has no dashed borders.
    $position places it (top, bottom or height classes) and $left is its x in points. Each dash is 4pt with a 3pt gap,
    so a line of a height PHP can't know is drawn with $dashes enough for it and clipped to its box: any corner radius
    makes EDGE clip a node to its shape, so a hairline radius is enough.
--}}
@php($dashes ??= 4)
<native:column class="absolute {{ $position }} left-[{{ $left }}] w-[1] gap-[3] rounded-[0.5]">
    @foreach (range(1, max(1, $dashes)) as $dash)
        <native:column class="w-[1] h-[4] {{ $colour }}" />
    @endforeach
</native:column>
