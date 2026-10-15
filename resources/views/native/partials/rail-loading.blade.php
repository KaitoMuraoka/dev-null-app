{{-- While the next page loads, a skeleton row keeps the rail running in Rail Gray, as the web's CommitSkeleton does. --}}
<native:row ref="loading-more" class="w-full bg-theme-surface" a11y-label="Loading more commits">
    <native:column :width="64" />
    <native:column class="flex-1">
        <native:column class="w-full min-h-[64] py-3 pr-4 gap-[10] opacity-[0.5]" animate-duration="900" :animate-loop="true">
            <native:column class="w-2/5 h-[12] mt-1 rounded bg-theme-wash" />
            <native:column class="w-4/5 h-[12] rounded bg-theme-wash" />
            <native:column class="w-3/5 h-[12] rounded bg-theme-wash" />
        </native:column>
        <native:column class="w-full h-[1] bg-theme-hairline" />
    </native:column>

    @include('native.partials.rail', ['previous' => \App\Devnull\Lane::Rail, 'tail' => \App\Devnull\Lane::Rail, 'lane' => \App\Devnull\Lane::Rail, 'node' => 'skeleton'])
</native:row>
