@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full pb-10">
        @include('native.partials.error')

        @if ($user)
            <native:pressable ref="my-profile" @press="openProfile" class="w-full bg-theme-surface" :press-opacity="0.7">
                <native:row class="w-full px-4 py-4 gap-3 items-center">
                    <native:stack :width="60" :height="60" class="rounded-full bg-theme-lane-{{ $user->lane() }}">
                        <native:stack :width="56" :height="56" class="rounded-full bg-theme-surface">
                            @include('native.partials.avatar', ['user' => $user, 'size' => 52])
                        </native:stack>
                    </native:stack>
                    <native:column class="flex-1 gap-1">
                        <native:text font="semibold" class="text-lg text-theme-ink">{{ $user->name }}</native:text>
                        <native:text font="mono" class="text-[13px] text-theme-ink-muted">{{ $user->handle() }}</native:text>
                    </native:column>
                    <native:icon name="forward" :size="16" class="text-theme-ink-muted" />
                </native:row>
            </native:pressable>
            <native:column class="w-full h-[1] bg-theme-hairline" />
        @endif

        <native:column class="w-full px-4 pt-6 pb-2">
            <native:text font="semibold" class="text-lg tracking-tight text-theme-ink">This device</native:text>
        </native:column>

        <native:column class="mx-4 rounded-lg border border-theme-hairline bg-theme-surface">
            <native:row class="w-full px-3 py-3 gap-3">
                <native:text font="mono" class="w-[88] text-[13px] text-theme-ink-muted">key</native:text>
                <native:text font="mono" class="flex-1 text-[13px] text-theme-ink">ssh-ed25519</native:text>
            </native:row>
            <native:column class="w-full h-[1] bg-theme-hairline" />
            <native:row ref="fingerprint" class="w-full px-3 py-3 gap-3 select-text">
                <native:text font="mono" class="w-[88] text-[13px] text-theme-ink-muted">fingerprint</native:text>
                <native:text font="mono" class="flex-1 text-[13px] text-theme-ink">{{ $fingerprint ?: 'null' }}</native:text>
            </native:row>
            <native:column class="w-full h-[1] bg-theme-hairline" />
            <native:row class="w-full px-3 py-3 gap-3 select-text">
                <native:text font="mono" class="w-[88] text-[13px] text-theme-ink-muted">public</native:text>
                <native:text ref="public-key" font="mono" class="flex-1 text-[13px] leading-snug text-theme-ink">{{ $publicKey ?: 'null' }}</native:text>
            </native:row>
            <native:column class="w-full h-[1] bg-theme-hairline" />
            <native:row class="w-full px-3 py-3 gap-3">
                <native:text font="mono" class="w-[88] text-[13px] text-theme-ink-muted">remote</native:text>
                <native:text font="mono" class="flex-1 text-[13px] text-theme-ink">{{ $remote }}</native:text>
            </native:row>
        </native:column>

        <native:column class="w-full px-4 pt-2">
            <native:text class="text-xs leading-relaxed text-theme-ink-muted">The secret half of this key lives in the Keychain and never leaves the phone. Press and hold the public key to copy it.</native:text>
        </native:column>

        <native:column class="w-full px-4 pt-4 gap-3">
            <native:button ref="share-key" variant="secondary" :ios-icon="Ios::SquareAndArrowUp" :android-icon="Android::Share" @press="shareKey" class="w-full">Share public key</native:button>
            <native:button ref="github-keys" variant="secondary" :ios-icon="Ios::Key" :android-icon="Android::Key" @press="openGitHubKeys" class="w-full">Your keys on GitHub</native:button>
        </native:column>

        <native:column class="w-full px-4 pt-8 gap-2">
            <native:button ref="forget-device" variant="destructive" @press="confirmForget" class="w-full">Forget this device</native:button>
            <native:text class="text-xs leading-relaxed text-theme-ink-muted">Deletes the key from this phone and signs you out.</native:text>
        </native:column>
    </native:column>
</native:scroll-view>
