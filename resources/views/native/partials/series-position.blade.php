{{-- A part's place in its series, like the web's SeriesPosition: "1/3" in mono on Commit Blue's soft wash. --}}
<native:column ref="series-position-{{ $commit->id }}" class="ml-1 px-2 py-[2] rounded-full bg-theme-primary-soft flex-shrink-0" a11y-label="Commit {{ $commit->seriesPosition }} of {{ $commit->seriesSize }} in a series">
    <native:text font="mono" class="text-xs text-theme-primary">{{ $commit->seriesPosition }}/{{ $commit->seriesSize }}</native:text>
</native:column>
