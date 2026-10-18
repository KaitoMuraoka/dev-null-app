{{--
    The web's StashLabel, beside a draft that was stashed and has come back: stash@{0} in mono, then drop, which throws
    the draft away. $drop is what drop presses, and drop is a full 44pt target.
--}}
@php($size ??= 'text-[13px]')
<native:row ref="{{ $ref }}" class="gap-1 items-center">
    <native:text font="mono" class="{{ $size }} text-theme-ink-muted" a11y-label="Restored your unsent draft">{{ 'stash@{0}' }}</native:text>
    <native:pressable ref="{{ $ref }}-drop" @press="{{ $drop }}" a11y-label="Discard the restored draft">
        <native:column class="min-w-[44] h-[36] items-center justify-center">
            <native:text font="mono" class="{{ $size }} text-theme-ink-muted underline">drop</native:text>
        </native:column>
    </native:pressable>
</native:row>
