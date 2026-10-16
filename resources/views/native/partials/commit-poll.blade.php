{{--
    A commit's poll, drawn as the web's CommitPoll. With results (you voted, it's yours, or it closed), each option is a
    hairline box with a bar of its share behind it, Blue Wash for the one you voted for with a check after its label,
    its percentage in mono on the right, and "4 votes · 3h left" under them all. Without results, the options are plain
    boxes over the time left.

    Polls are read-only here: devnull has no way to vote from the app yet, so where the web offers radio buttons and
    Vote, the options are the same plain boxes and nothing is tappable. $static is the quote card's copy, which never is.
--}}
@php($poll = $commit->poll)
@php($static ??= false)
<native:column ref="poll-{{ $commit->id }}" class="w-full mt-3 gap-2" a11y-label="{{ $poll->showsResults() ? 'Poll results' : 'Poll options' }}">
    @foreach ($poll->options as $option)
        @if ($poll->showsResults())
            <native:column ref="poll-option-{{ $option->id }}" class="w-full rounded-md border border-theme-hairline" a11y-label="{{ $option->label }}{{ $option->voted ? ', your vote' : '' }}, {{ $option->share() }}%, {{ $option->votesLabel() }}">
                <native:row class="absolute top-0 bottom-0 left-0 right-0">
                    <native:column :flexGrow="$option->share()" :flexBasis="0" class="h-full rounded-md {{ $option->voted ? 'bg-theme-primary-soft' : 'bg-theme-wash' }}" />
                    <native:column :flexGrow="100 - $option->share()" :flexBasis="0" />
                </native:row>
                <native:row class="w-full px-3 py-2 gap-[6] items-center">
                    <native:text :font="$option->voted ? 'medium' : null" class="flex-shrink text-[15px] text-theme-ink">{{ $option->label }}</native:text>
                    @if ($option->voted)
                        <native:icon ref="poll-voted-{{ $option->id }}" :ios="\App\Icons\Lucide::Check->ios()" :android="\App\Icons\Lucide::Check->android()" :size="16" class="text-theme-primary" />
                    @endif
                    <native:spacer />
                    <native:text font="mono" class="text-[13px] text-theme-ink">{{ $option->share() }}%</native:text>
                </native:row>
            </native:column>
        @else
            <native:column ref="poll-option-{{ $option->id }}" class="w-full px-3 py-2 rounded-md border border-theme-hairline">
                <native:text class="w-full text-[15px] text-theme-ink">{{ $option->label }}</native:text>
            </native:column>
        @endif
    @endforeach

    <native:text ref="poll-summary-{{ $commit->id }}" font="mono" class="text-[13px] text-theme-ink-muted">{{ $poll->showsResults() ? $poll->summary() : $poll->status() }}</native:text>
</native:column>
