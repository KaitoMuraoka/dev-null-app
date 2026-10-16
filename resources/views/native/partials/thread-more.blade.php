{{--
    "Show more" at the foot of a thread, as the web's: a muted rail dot where the next avatar would be, and the words.
    It sits inside the last reply's row, under the reply, so the rail beside it never breaks: a row of its own is
    shorter than the list's shortest, and the list pads it out under its hairline.
--}}
<native:pressable ref="more-replies-{{ $commit->id }}" @press="{{ $more }}" class="w-full" a11y-label="Show more replies">
    <native:row class="w-full pt-[6] pb-3 pr-4 gap-2 {{ $indent > 0 ? 'pl-['.$indent.']' : '' }} items-center">
        <native:stack :width="24" :height="24" center>
            <native:stack :width="18" :height="18" center class="rounded-full bg-theme-ink-muted">
                <native:stack :width="14" :height="14" center class="rounded-full bg-theme-surface">
                    <native:column :width="8" :height="8" class="rounded-full bg-theme-ink-muted" />
                </native:stack>
            </native:stack>
        </native:stack>
        <native:text class="text-[13px] text-theme-ink-muted">Show more</native:text>
    </native:row>
</native:pressable>
