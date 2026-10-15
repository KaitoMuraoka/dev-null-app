@use('App\Icons\Ios')
@use('App\Icons\Android')
@use('App\Devnull\GitHubStep')

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
            @if ($canUseGitHub)
                <native:text class="text-[15px] leading-relaxed text-theme-ink">
                    The public half goes on GitHub once, as a signing key. devnull checks it against your account, and that's the whole sign-in. It can't touch your repositories.
                </native:text>
            @else
                <native:text class="text-[15px] leading-relaxed text-theme-ink">
                    Add the public half to GitHub once, as a signing key. devnull checks it against your account, and that's the whole sign-in. It can't touch your repositories.
                </native:text>
            @endif
        </native:column>

        @if ($canUseGitHub)
            <native:column ref="github" class="w-full gap-3">
                @if ($step === GitHubStep::Authorizing)
                    <native:text class="text-[15px] leading-relaxed text-theme-ink">Enter this code on GitHub.</native:text>
                    <native:column ref="user-code" class="w-full py-5 items-center rounded-lg border border-theme-hairline bg-theme-surface select-text">
                        <native:text font="mono" class="text-4xl tracking-widest text-theme-ink">{{ $userCode }}</native:text>
                    </native:column>
                    <native:button ref="open-device-page" variant="primary" :ios-icon="Ios::ArrowUpRightSquare" :android-icon="Android::OpenInNew" @press="openDevicePage" class="w-full">Enter the code on GitHub</native:button>
                    <native:button ref="share-code" variant="secondary" :ios-icon="Ios::SquareAndArrowUp" :android-icon="Android::Share" @press="shareCode" class="w-full">Share code</native:button>
                    <native:row class="w-full gap-2 items-center">
                        <native:activity-indicator size="sm" />
                        <native:text font="mono" class="text-[13px] text-theme-ink-muted">Waiting for GitHub…</native:text>
                    </native:row>
                    @if ($githubError)
                        <native:text ref="github-error" font="mono" class="text-[13px] text-theme-destructive">{{ $githubError }}</native:text>
                    @endif
                    <native:text class="text-xs leading-relaxed text-theme-ink-muted">Press and hold the code to copy it. It works for {{ $codeMinutes }} minutes, and this screen carries on by itself once GitHub has it.</native:text>
                    <native:button ref="cancel-github" variant="ghost" @press="cancelGitHub" class="w-full">Cancel</native:button>
                @elseif ($step === GitHubStep::Expired)
                    <native:text ref="github-error" font="mono" class="text-[13px] text-theme-destructive">fatal: the code expired before GitHub saw it.</native:text>
                    <native:text class="text-[15px] leading-relaxed text-theme-ink">A code lasts {{ $codeMinutes }} minutes. Ask for a new one and enter it on GitHub.</native:text>
                    <native:button ref="start-again" variant="primary" @press="connectGitHub" class="w-full">Start again</native:button>
                @elseif ($step === GitHubStep::Listing)
                    <native:row class="w-full gap-2 items-center">
                        <native:activity-indicator size="sm" />
                        <native:text ref="github-status" font="mono" class="text-[13px] text-theme-ink-muted">Waiting for GitHub to list the key…</native:text>
                    </native:row>
                    <native:text class="text-xs leading-relaxed text-theme-ink-muted">The key is on {{ '@'.$login }}'s GitHub account. GitHub can take a few seconds to show it to devnull.</native:text>
                @else
                    <native:text class="text-[15px] leading-relaxed text-theme-ink">
                        devnull asks GitHub for one thing: permission to add this phone's signing key. The token is dropped as soon as the key is added.
                    </native:text>
                    @if ($githubNotice)
                        <native:text ref="github-notice" class="text-sm leading-relaxed text-theme-ink-muted">{{ $githubNotice }}</native:text>
                    @endif
                    @if ($githubError)
                        <native:text ref="github-error" font="mono" class="text-[13px] text-theme-destructive">{{ $githubError }}</native:text>
                    @endif
                    <native:button ref="connect-github" variant="primary" :ios-icon="Ios::Key" :android-icon="Android::Key" :disabled="$publicKey === ''" @press="connectGitHub" class="w-full">Connect GitHub</native:button>
                @endif
            </native:column>

            <native:column class="w-full h-[1] bg-theme-hairline" />

            <native:text font="semibold" class="text-lg tracking-tight text-theme-ink">Add the key yourself</native:text>
        @endif

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

            <native:button ref="connect" :variant="$canUseGitHub ? 'secondary' : 'primary'" :disabled="trim($username) === '' || $publicKey === ''" @press="connect" class="w-full">Connect</native:button>
        </native:column>
    </native:column>
</native:scroll-view>
