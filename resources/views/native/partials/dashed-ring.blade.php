{{--
    A 2pt ring drawn in dashes, standing in for the web's `border-dashed` since EDGE has no dashed borders: each dash
    is placed on the circle and turned to follow it.
--}}
@php($segments = 16)
@php($radius = $size / 2 - 1)
<native:column :width="$size" :height="$size" class="absolute top-0 left-0">
    @foreach (range(0, $segments - 1) as $index)
        @php($angle = 360 / $segments * $index)
        @php($x = $size / 2 + $radius * sin(deg2rad($angle)))
        @php($y = $size / 2 - $radius * cos(deg2rad($angle)))
        <native:column class="absolute top-[{{ round($y - 1, 2) }}] left-[{{ round($x - 2.5, 2) }}] w-[5] h-[2] rounded-full {{ $colour }}" :rotate="$angle" />
    @endforeach
</native:column>
