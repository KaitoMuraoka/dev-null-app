{{-- One commit on the graph: a lane-coloured ring with a gap punched in the surface colour. --}}
@php($filled ??= true)
<native:stack :width="18" :height="18" center class="rounded-full bg-theme-lane-{{ $lane }}">
    <native:stack :width="14" :height="14" center class="rounded-full bg-theme-surface">
        @if ($filled)
            <native:column :width="8" :height="8" class="rounded-full bg-theme-lane-{{ $lane }}" />
        @endif
    </native:stack>
</native:stack>
