{{--
    An author on the rail, as the web's RailAvatar: their 40pt avatar in a 2pt ring of their lane, with a 2pt gap in
    the surface colour. A staged one (a commit not made yet) draws the ring in dashes.
--}}
@php($staged ??= false)
@if ($staged)
    <native:column ref="staged-avatar" :width="48" :height="48" class="rounded-full bg-theme-surface">
        @include('native.partials.dashed-ring', ['size' => 48, 'colour' => 'bg-theme-lane-'.$lane])
        <native:column class="absolute top-[4] left-[4]">
            @include('native.partials.avatar', ['user' => $user, 'size' => 40])
        </native:column>
    </native:column>
@else
    <native:stack :width="48" :height="48" center class="rounded-full bg-theme-lane-{{ $lane }}">
        <native:stack :width="44" :height="44" center class="rounded-full bg-theme-surface">
            @include('native.partials.avatar', ['user' => $user, 'size' => 40])
        </native:stack>
    </native:stack>
@endif
