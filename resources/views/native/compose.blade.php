<native:column class="w-full h-full bg-theme-surface">
    <native:row class="w-full">
        <native:column :width="44" />

        <native:column class="flex-1 pt-[20] pr-4 pb-3 gap-3">
            <native:bare-text-input
                ref="body"
                class="w-full text-theme-ink"
                placeholder="What did you ship?"
                native:model="body"
                multiline
                :min-lines="4"
                :max-lines="12"
                :max-length="280"
                size="lg"
                a11y-label="Commit message"
            />
        </native:column>

        {{-- The staged node: hollow until the commit is long enough, then filled with your lane. --}}
        @include('native.partials.rail', ['lane' => $me?->lane() ?? 0, 'filled' => $canCommit, 'tail' => 'rail', 'nodeTop' => 21])
    </native:row>

    <native:column class="w-full h-[1] bg-theme-hairline" />

    @include('native.partials.error')

    <native:row class="w-full px-4 py-3 gap-3 items-center justify-end">
        <native:text ref="budget" font="mono" class="text-[13px] {{ $remaining <= 0 ? 'text-theme-destructive' : 'text-theme-ink-muted' }}" a11y-label="{{ $remaining }} characters left">{{ $remaining }}</native:text>
        <native:button ref="commit" variant="primary" :disabled="! $canCommit" @press="commit">Commit</native:button>
    </native:row>
</native:column>
