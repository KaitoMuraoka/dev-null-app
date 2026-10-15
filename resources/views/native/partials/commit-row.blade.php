{{--
    A commit on the graph rail: [rail | avatar, header, body, media, actions].
    The segment above the node takes the previous author's lane, the one below takes this author's.
--}}
@php($previousLane ??= null)
@if ($commit->isDeleted())
    @include('native.partials.commit-tombstone')
@else
<native:pressable ref="commit-{{ $commit->id }}" @press="openCommit('{{ $commit->id }}')" class="w-full bg-theme-surface" :press-opacity="0.7">
    <native:row class="w-full">
        <native:column :width="44" />

        <native:column class="flex-1">
            <native:row class="w-full gap-3 py-3 pr-4">
                <native:pressable @press="openProfile('{{ $commit->user->username }}')" a11y-label="{{ $commit->user->name }}'s profile">
                    @include('native.partials.avatar', ['user' => $commit->user, 'size' => 40])
                </native:pressable>

                <native:column class="flex-1 gap-1">
                    <native:row class="w-full gap-1 items-center">
                        <native:text font="semibold" class="text-[15px] text-theme-ink flex-shrink" :max-lines="1">{{ $commit->user->name }}</native:text>
                        <native:text font="mono" class="text-[13px] text-theme-ink-muted flex-shrink" :max-lines="1">{{ $commit->user->handle() }}</native:text>
                        <native:text class="text-[13px] text-theme-ink-muted">·</native:text>
                        <native:text font="mono" class="text-[13px] text-theme-ink-muted flex-shrink-0">{{ \App\Devnull\Display::relativeTime($commit->createdAt) }}</native:text>
                        @unless ($commit->isApproved())
                            <native:column class="ml-1 px-2 py-[2] rounded-full bg-theme-pending-soft flex-shrink-0">
                                <native:text font="mono" class="text-xs text-theme-pending">{{ $commit->status }}</native:text>
                            </native:column>
                        @endunless
                    </native:row>

                    @include('native.partials.commit-body')
                    @include('native.partials.commit-media')
                    @include('native.partials.commit-actions')
                </native:column>
            </native:row>
            <native:column class="w-full h-[1] bg-theme-hairline" />
        </native:column>

        @include('native.partials.rail', ['previous' => $previousLane, 'lane' => $commit->lane(), 'tail' => $commit->lane()])
    </native:row>
</native:pressable>
@endif
