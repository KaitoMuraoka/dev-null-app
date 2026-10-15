{{-- Where a deleted commit's avatar was: an empty Rail Gray ring. The web dashes it; EDGE draws no dashed borders. --}}
<native:stack :width="$size" :height="$size" center class="rounded-full bg-theme-rail">
    <native:stack :width="$size - 4" :height="$size - 4" class="rounded-full bg-theme-surface" />
</native:stack>
