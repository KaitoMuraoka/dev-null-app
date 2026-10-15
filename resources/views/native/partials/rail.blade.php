{{--
    The graph rail, drawn behind a row's 44pt gutter. Place it inside a row that reserves the gutter.
    $previous and $tail are a lane number, 'rail' for Rail Gray, or null for no segment.
    The node sits so its centre lines up with a 40pt avatar padded 12pt from the top.
--}}
@php($previous ??= null)
@php($tail ??= null)
@php($node ??= true)
@php($filled ??= true)
@php($nodeTop ??= 23)
@php($center = $nodeTop + 9)
@php($segment = fn (int|string $lane): string => $lane === 'rail' ? 'bg-theme-rail' : 'bg-theme-lane-'.$lane)

@if ($previous !== null)
    <native:column class="absolute top-0 left-[21] w-[2] h-[{{ $center }}] {{ $segment($previous) }}" />
@endif
@if ($tail !== null)
    <native:column class="absolute top-[{{ $center }}] bottom-0 left-[21] w-[2] {{ $segment($tail) }}" />
@endif
@if ($node)
    <native:column class="absolute top-[{{ $nodeTop }}] left-[13]">
        @include('native.partials.rail-node', ['lane' => $lane, 'filled' => $filled])
    </native:column>
@endif
