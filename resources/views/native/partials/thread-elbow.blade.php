{{--
    Where a thread leaves the rail, as the web's thread elbow from #121: a dashed line in the commit's lane down from
    the rail ($from), round a 12pt corner, and across to the first reply's avatar ($to), level with its middle. A
    nested reply's elbow leaves the spine of the reply above it the same way. EDGE has no dashed
    borders, so each dash is placed, and the ones on the corner are turned to follow it, as in dashed-ring.
--}}
@php($colour = $lane === \App\Devnull\Lane::Rail ? 'bg-theme-rail' : 'bg-theme-lane-'.$lane)
@php($rail = $from ?? 31.5)
@php($to ??= 64)
@php($radius = 12)
@php($level = 22)
<native:column ref="thread-elbow" class="absolute top-0 left-0 w-[{{ $to }}] h-[{{ $level + 1 }}]">
    @foreach ([0, 7] as $y)
        <native:column class="absolute {{ $y === 0 ? 'top-0' : 'top-['.$y.']' }} left-[{{ $rail }}] w-[1] h-[4] {{ $colour }}" />
    @endforeach
    @foreach ([160, 130, 100] as $angle)
        @php($x = $rail + $radius + $radius * cos(deg2rad($angle)))
        @php($y = $level - $radius + $radius * sin(deg2rad($angle)))
        <native:column class="absolute top-[{{ round($y - 2, 2) }}] left-[{{ round($x - 0.5, 2) }}] w-[1] h-[4] {{ $colour }}" :rotate="180 - $angle" />
    @endforeach
    @for ($x = $rail + $radius + 2; $x + 4 <= $to - 2.5; $x += 7)
        <native:column class="absolute top-[{{ $level - 0.5 }}] left-[{{ $x }}] w-[4] h-[1] {{ $colour }}" />
    @endfor
</native:column>
