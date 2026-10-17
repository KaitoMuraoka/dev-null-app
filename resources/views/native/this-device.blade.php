@use('App\Icons\Ios')
@use('App\Icons\Android')
@use('App\Icons\Lucide')

{{-- Laid out like the web's profile settings: a section each, with its heading, on the surface. --}}
<native:scroll-view class="w-full h-full bg-theme-surface">
    <native:column class="w-full pt-6 pb-10">
        @include('native.partials.error')
        @include('native.partials.refresh-failed')

        @if ($user !== null)
            <native:column ref="profile-information" class="w-full">
                @include('native.partials.settings-heading', [
                    'title' => 'Profile information',
                    'description' => 'Mirrored from GitHub when you sign in. To change any of it, change it on GitHub, then sync it here.',
                ])

                <native:row ref="profile-identity" class="w-full px-4 pt-5 gap-4 items-center">
                    @include('native.partials.rail-avatar', ['user' => $user, 'lane' => $savedLane, 'size' => 56])
                    <native:column class="flex-1 gap-[2]">
                        <native:text font="semibold" class="text-base text-theme-ink" :max-lines="1">{{ $user->name }}</native:text>
                        <native:text font="mono" class="text-sm text-theme-ink-muted" :max-lines="1">{{ $user->handle() }}</native:text>
                    </native:column>
                </native:row>

                {{-- The web's hairline table: mono labels, and null in muted mono for what GitHub doesn't have. --}}
                <native:column ref="profile-fields" class="mx-4 mt-5 rounded-lg border border-theme-hairline bg-theme-surface">
                    @foreach ($fields as $field)
                        @if (! $loop->first)
                            <native:column class="w-full h-[1] bg-theme-hairline" />
                        @endif
                        <native:row ref="field-{{ $field['label'] }}" class="w-full px-3 py-3 gap-3">
                            <native:text font="mono" class="w-[88] text-[13px] text-theme-ink-muted">{{ $field['label'] }}</native:text>
                            @if ($field['value'] === null)
                                <native:text font="mono" class="flex-1 text-[13px] text-theme-ink-muted">null</native:text>
                            @else
                                <native:text class="flex-1 text-sm text-theme-ink">{{ $field['value'] }}</native:text>
                            @endif
                        </native:row>
                    @endforeach
                </native:column>

                <native:column class="w-full px-4 pt-5 gap-2">
                    <native:button ref="edit-on-github" variant="primary" :ios-icon-trailing="Lucide::ArrowUpRight->ios()" :android-icon-trailing="Lucide::ArrowUpRight->android()" @press="editOnGitHub" class="w-full">Edit on GitHub</native:button>
                    {{-- The label stays put while it spins, so the button keeps its size. --}}
                    <native:button ref="sync-github" variant="secondary" :ios-icon="Lucide::RefreshCw->ios()" :android-icon="Lucide::RefreshCw->android()" :loading="$syncing" :disabled="$syncing" @press="syncProfile" class="w-full">Sync from GitHub</native:button>
                    <native:text ref="synced-at" class="text-xs leading-relaxed text-theme-ink-muted">{{ $syncedAt }}</native:text>
                    @if ($syncError)
                        <native:text ref="sync-error" class="text-sm text-theme-destructive">{{ $syncError }}</native:text>
                    @endif
                </native:column>
            </native:column>
        @endif

        @if ($offersApple)
            <native:column ref="apple" class="w-full pt-10">
                @include('native.partials.settings-heading', ['title' => 'Apple ID'])

                <native:column class="mx-4 mt-4 rounded-lg border border-theme-hairline bg-theme-surface">
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

        <native:column ref="this-phone" class="w-full pt-10">
            @include('native.partials.settings-heading', [
                'title' => 'This phone',
                'description' => 'Signed in with a key that stays on this phone. dev-null.sh lists it by this fingerprint.',
            ])

            {{-- dev-null.sh lists each phone by name and fingerprint, so this is how you tell which one is this phone. --}}
            <native:column class="mx-4 mt-4 rounded-lg border border-theme-hairline bg-theme-surface">
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
        </native:column>

        <native:column class="w-full px-4 pt-8 gap-2">
            <native:button ref="sign-out" variant="secondary" @press="confirmSignOut" class="w-full">Sign out</native:button>
            <native:text class="text-xs leading-relaxed text-theme-ink-muted">Deletes the key from this phone and signs you out.</native:text>
        </native:column>

        <native:column ref="delete-section" class="w-full pt-12">
            @include('native.partials.settings-heading', [
                'title' => 'Delete account',
                'description' => "Removes your /dev/null account, every commit and reply you've made, and everyone you follow. Replies others left under your commits go too. Replies under your replies stay, hanging off a [deleted] tombstone. Your GitHub account is not touched.",
            ])

            <native:column class="w-full px-4 pt-4">
                <native:button ref="delete-account" variant="destructive" @press="confirmDeleteAccount" class="w-full">Delete account</native:button>
            </native:column>
        </native:column>
    </native:column>
</native:scroll-view>
