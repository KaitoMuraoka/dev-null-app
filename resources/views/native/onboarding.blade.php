@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:scroll-view fill class="safe-area bg-theme-background">
    <native:column class="w-full px-6 pt-10 pb-12 gap-6">
        {{-- The Log header's lockup, 40pt high as a title. Width and height are both set so it sits left, not centred. --}}
        <native:image ref="logo" :src="$logo" :width="150" :height="40" alt="devnull" />

        <native:text ref="intro" class="text-[15px] leading-relaxed text-theme-ink">This phone makes its own key. You approve it once on dev-null.sh, and that signs you in.</native:text>

        @if ($notice)
            <native:column ref="notice" class="w-full p-3 rounded-lg border border-theme-hairline bg-theme-surface">
                <native:text class="text-sm leading-relaxed text-theme-ink">{{ $notice }}</native:text>
            </native:column>
        @endif

        @if ($error)
            <native:text ref="error" font="mono" class="text-[13px] leading-snug text-theme-destructive">{{ $error }}</native:text>
        @endif

        <native:button ref="sign-in" variant="primary" :ios-icon="Ios::PersonBadgeKey" :android-icon="Android::Login" :disabled="$fingerprint === ''" @press="signIn" class="w-full">{{ $error && $fingerprint !== '' ? 'Try again' : 'Sign in with GitHub' }}</native:button>
    </native:column>
</native:scroll-view>
