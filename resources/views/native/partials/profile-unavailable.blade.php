{{--
    A profile across a block, worded as the web's unavailable page. The one who blocked is told so, with the person's
    avatar when it's known and a way to the web's muted and blocked people, where a block is undone, since devnull has
    no unblock for the app yet. The web greys the avatar out; EDGE has no filter for that, so it's faded instead. The
    one who was blocked is only told that it isn't available.
--}}
@php($name = $profile?->user->name)
<native:column ref="profile-unavailable" class="w-full items-center px-6 py-16 gap-3 bg-theme-surface">
    @if ($isBlocked)
        @if ($profile)
            <native:column class="mb-2 opacity-50">
                @include('native.partials.avatar', ['user' => $profile->user, 'size' => 64])
            </native:column>
        @endif
        <native:text font="mono" class="text-[13px] text-center text-theme-ink-muted">fatal: {{ '@'.$username }} is blocked</native:text>
        <native:text class="text-[15px] text-center leading-relaxed text-theme-ink max-w-[320]">You blocked {{ $name ?? '@'.$username }}. Neither of you can follow, reply to, or star the other, or see each other's commits.</native:text>
        <native:button ref="muted-and-blocked" variant="ghost" size="sm" @press="openSafety">Muted and blocked people</native:button>
    @else
        <native:text font="mono" class="text-[13px] text-center text-theme-ink-muted">fatal: {{ '@'.$username }} is unavailable</native:text>
        <native:text class="text-[15px] text-center leading-relaxed text-theme-ink max-w-[320]">This profile isn't available.</native:text>
    @endif
</native:column>
