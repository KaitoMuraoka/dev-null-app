{{-- Empty states speak in git's voice: a mono line, then a plain sentence or two. --}}
<native:column ref="empty-state" class="w-full items-center px-8 py-16 gap-3 bg-theme-surface">
    <native:text font="mono" class="text-[13px] text-center text-theme-ink-muted">{{ $command }}</native:text>
    <native:text class="text-[15px] text-center leading-relaxed text-theme-ink max-w-[320]">{{ $message }}</native:text>
</native:column>
