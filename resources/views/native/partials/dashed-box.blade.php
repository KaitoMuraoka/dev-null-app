{{--
    A 1pt dashed border drawn over the box it's placed last in, standing in for the web's `border-dashed`, since EDGE
    has no dashed borders: a row of dashes along the top and bottom, a column down each side. The corners are square
    where the web rounds them, as dashes can't follow a curve here without being placed one by one.
--}}
@php($colour ??= 'bg-theme-hairline')
@foreach (['top-0', 'bottom-0'] as $edge)
    <native:row class="absolute {{ $edge }} left-0 right-0 h-[1] gap-[3]">
        @foreach (range(1, 40) as $dash)
            <native:column class="flex-1 h-[1] {{ $colour }}" />
        @endforeach
    </native:row>
@endforeach
@foreach (['left-0', 'right-0'] as $edge)
    <native:column class="absolute top-0 bottom-0 {{ $edge }} w-[1] gap-[3]">
        @foreach (range(1, 6) as $dash)
            <native:column class="w-[1] flex-1 {{ $colour }}" />
        @endforeach
    </native:column>
@endforeach
