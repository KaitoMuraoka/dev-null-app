{{--
    The people in a notification group, stacked as the web stacks them: up to three 32pt avatars, each ringed in the
    surface colour and tucked 8pt under the one before, the newest on top. EDGE has no z-index and draws later
    siblings on top, so the avatars sit at fixed offsets and are drawn oldest first.
--}}
@php($step = 24)
<native:column ref="actors-{{ $id }}" :width="(count($actors) - 1) * $step + 36" :height="36">
    @foreach (array_reverse($actors, true) as $index => $actor)
        <native:column class="absolute top-0 left-[{{ $index * $step }}]">
            <native:stack :width="36" :height="36" center class="rounded-full bg-theme-surface">
                @include('native.partials.avatar', ['user' => $actor, 'size' => 32])
            </native:stack>
        </native:column>
    @endforeach
</native:column>
