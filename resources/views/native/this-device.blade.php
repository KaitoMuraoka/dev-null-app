@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full pt-6 pb-10">
        @include('native.partials.error')
        @include('native.partials.refresh-failed')

        {{-- dev-null.sh lists each phone by name and fingerprint, so this is how you tell which one is this phone. --}}
        <native:column class="mx-4 rounded-lg border border-theme-hairline bg-theme-surface">
            <native:row ref="fingerprint" class="w-full px-3 py-3 gap-3 select-text">
                <native:text font="mono" class="w-[88] text-[13px] text-theme-ink-muted">fingerprint</native:text>
                <native:text font="mono" class="flex-1 text-[13px] text-theme-ink">{{ $fingerprint ?: 'null' }}</native:text>
            </native:row>
            @if ($remote !== null)
                <native:column class="w-full h-[1] bg-theme-hairline" />
                <native:row ref="remote" class="w-full px-3 py-3 gap-3">
                    <native:text font="mono" class="w-[88] text-[13px] text-theme-ink-muted">remote</native:text>
                    <native:text font="mono" class="flex-1 text-[13px] text-theme-ink">{{ $remote }}</native:text>
                </native:row>
            @endif
        </native:column>

        <native:column class="w-full px-4 pt-4">
            <native:button ref="devices" variant="secondary" :ios-icon="Ios::Iphone" :android-icon="Android::Smartphone" @press="openDevices" class="w-full">Your devices on dev-null.sh</native:button>
        </native:column>

        @if ($offersApple)
            <native:column ref="apple" class="w-full pt-8">
                <native:column class="mx-4 rounded-lg border border-theme-hairline bg-theme-surface">
                    <native:row class="w-full px-3 py-3 gap-3">
                        <native:text font="mono" class="w-[88] text-[13px] text-theme-ink-muted">apple id</native:text>
                        <native:text ref="apple-status" font="mono" class="flex-1 text-[13px] text-theme-ink">{{ $appleStatus }}</native:text>
                    </native:row>
                </native:column>

                <native:column class="w-full px-4 pt-2">
                    <native:text class="text-xs leading-relaxed text-theme-ink-muted">A quicker way back in on a new phone. The account is still your GitHub one.</native:text>
                </native:column>

                @if ($appleLinked === false)
                    {{-- Apple's black button (white in dark mode, the theme's accent) with its logo, as Apple's guidelines ask. --}}
                    <native:column class="w-full px-4 pt-4">
                        <native:button ref="link-apple" variant="accent" :ios-icon="Ios::AppleLogo" @press="linkApple" class="w-full">Sign in with Apple</native:button>
                    </native:column>
                @elseif ($appleLinked === true)
                    <native:column class="w-full px-4 pt-4">
                        <native:button ref="unlink-apple" variant="secondary" @press="confirmUnlinkApple" class="w-full">Unlink Apple ID</native:button>
                    </native:column>
                @endif
            </native:column>
        @endif

        <native:column class="w-full px-4 pt-8 gap-2">
            <native:button ref="sign-out" variant="secondary" @press="confirmSignOut" class="w-full">Sign out</native:button>
            <native:text class="text-xs leading-relaxed text-theme-ink-muted">Deletes the key from this phone and signs you out.</native:text>
        </native:column>

        <native:column class="w-full px-4 pt-12 gap-2">
            <native:button ref="delete-account" variant="destructive" @press="confirmDeleteAccount" class="w-full">Delete account</native:button>
            <native:text class="text-xs leading-relaxed text-theme-ink-muted">Removes your account and everything in it from /dev/null.</native:text>
        </native:column>
    </native:column>
</native:scroll-view>
