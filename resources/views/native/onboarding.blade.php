@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:scroll-view fill class="safe-area bg-theme-background">
    <native:column class="w-full px-6 pt-10 pb-12 gap-6">
        <native:column class="gap-2">
            <native:text font="semibold" class="text-4xl tracking-tight text-theme-ink">devnull</native:text>
            <native:text font="mono" class="text-[13px] text-theme-ink-muted">$ ssh-keygen -t ed25519 -C devnull-mobile</native:text>
        </native:column>

        @if ($notice)
            <native:column ref="notice" class="w-full p-3 rounded-lg border border-theme-hairline bg-theme-surface">
                <native:text class="text-sm text-theme-destructive">{{ $notice }}</native:text>
            </native:column>
        @endif

        <native:column class="gap-3">
            <native:text class="text-[15px] leading-relaxed text-theme-ink">
                This phone made its own SSH key. The secret half stays in the Keychain and never leaves it.
            </native:text>
            <native:text class="text-[15px] leading-relaxed text-theme-ink">
                Add the public half to GitHub once, as a signing key. devnull checks it against your account, and that's the whole sign-in. It can't touch your repositories.
            </native:text>
        </native:column>

        <native:column class="w-full gap-3">
            <native:column ref="public-key" class="w-full p-3 gap-2 rounded-lg border border-theme-hairline bg-theme-surface select-text">
                <native:text font="mono" class="text-[13px] leading-snug text-theme-ink">{{ $publicKey }}</native:text>
                <native:text font="mono" class="text-xs text-theme-ink-muted">{{ $fingerprint }}</native:text>
            </native:column>
            <native:text class="text-xs text-theme-ink-muted">Press and hold the key to copy it, or share it to wherever you'll paste it.</native:text>
            <native:row class="w-full gap-3">
                <native:column class="flex-1">
                    <native:button ref="share-key" variant="secondary" :ios-icon="Ios::SquareAndArrowUp" :android-icon="Android::Share" @press="shareKey" class="w-full">Share key</native:button>
                </native:column>
                <native:column class="flex-1">
                    <native:button ref="open-github" variant="secondary" :ios-icon="Ios::ArrowUpRightSquare" :android-icon="Android::OpenInNew" @press="openGitHub" class="w-full">Open GitHub</native:button>
                </native:column>
            </native:row>
            <native:text class="text-xs leading-relaxed text-theme-ink-muted">On GitHub, choose New SSH key, set the key type to Signing Key, and paste.</native:text>
        </native:column>

        <native:column class="w-full gap-3">
            <native:outlined-text-input
                ref="username"
                label="GitHub username"
                placeholder="octocat"
                native:model.debounce.150ms="username"
                keyboard="url"
                autocapitalize="none"
                font="mono"
                @submit="connect"
            />

            @if ($error)
                <native:text ref="error" class="text-sm text-theme-destructive">{{ $error }}</native:text>
            @endif

            <native:button ref="connect" variant="primary" :disabled="trim($username) === '' || $publicKey === ''" @press="connect" class="w-full">Connect</native:button>
        </native:column>
    </native:column>
</native:scroll-view>
