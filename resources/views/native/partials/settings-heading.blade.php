{{-- A settings section's heading, as the web's: its title, and a line on what it's for. --}}
@php($description ??= null)
<native:column class="w-full px-4 gap-1">
    <native:text font="semibold" class="text-lg text-theme-ink">{{ $title }}</native:text>
    @if ($description)
        <native:text class="text-sm leading-relaxed text-theme-ink-muted">{{ $description }}</native:text>
    @endif
</native:column>
