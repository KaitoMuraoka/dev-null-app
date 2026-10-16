@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:column fill class="safe-area bg-theme-background">
    {{-- The lockup, two thirds of the screen wide at its own 15:4, centred in the space above the buttons. --}}
    <native:column ref="logo-space" class="w-full flex-1 items-center justify-center">
        <native:image ref="logo" :src="$logo" alt="devnull" class="w-2/3 aspect-[15/4]" />
    </native:column>

    <native:column ref="actions" class="w-full px-6 pb-4 gap-3">
        @if ($error)
            <native:text ref="error" font="mono" class="w-full text-center text-[13px] leading-snug text-theme-destructive">{{ $error }}</native:text>
        @endif

        @if ($appleError)
            <native:text ref="apple-error" font="mono" class="w-full text-center text-[13px] leading-snug text-theme-destructive">{{ $appleError }}</native:text>
        @endif

        <native:button ref="sign-in" variant="primary" :ios-icon="Ios::PersonBadgeKey" :android-icon="Android::Login" :disabled="$fingerprint === ''" @press="signIn" class="w-full">{{ $error && $fingerprint !== '' ? 'Try again' : 'Sign in with GitHub' }}</native:button>

        {{-- Apple's black button (white in dark mode, the theme's accent) with its logo, the same size as GitHub's, as Apple's guidelines ask. --}}
        @if ($offersApple)
            <native:button ref="sign-in-apple" variant="accent" :ios-icon="Ios::AppleLogo" :disabled="$fingerprint === ''" @press="signInWithApple" class="w-full">Sign in with Apple</native:button>
        @endif
    </native:column>
</native:column>
