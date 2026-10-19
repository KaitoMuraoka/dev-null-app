{{--
    The sheet under a commit (see OpensCommitSheet). Quote opens it as a composer: the field, focused, with the commit
    being quoted under it as a static card, then the counter and Commit, which waits for a body long enough to make.
    If devnull says no, why shows here and the draft stays. The Log's compose button opens the same composer for a new
    commit, without the card. A draft stashed last time comes back in it, with the web's stash@{0} and drop on the left
    of Commit's row.

    Report commit, in someone else's published commit's More menu, opens it as the web's Report dialog: the private,
    not-hidden line, the commit's body clamped to three lines, the four reasons, optional details, and Send report,
    which waits for a reason. Once devnull has the report, the sheet says so, with Done. If devnull says no, why shows
    here and the form keeps what you wrote.

    A new commit can become a `/feature` or `/bug` issue, as on the web (see DraftsIssues): the command's badge over the
    field, its placeholder in it, and Open issue for Commit. Opened, the sheet says so, with View issue and Done.

    It's full height for the forms, and short for the receipts. It's always on the screen, shown or not, so iOS can
    slide it away and say when it's gone.
--}}
<native:bottom-sheet ref="sheet" :visible="$sheetOpen" :detents="in_array($sheetView, ['reported', 'issued'], true) ? '0.3' : 'large'" @dismiss="commitSheetClosed">
    @if ($sheetCommit || in_array($sheetView, ['compose', 'issued'], true))
        @if (in_array($sheetView, ['quote', 'compose'], true))
            @php($sheetLength = mb_strlen(trim($sheetBody)))
            @php($quoting = $sheetView === 'quote')
            @php($issueCommand = $sheetIssue === null ? null : \App\Devnull\IssueCommand::from($sheetIssue))
            <native:scroll-view ref="sheet-composer" class="w-full flex-1">
                <native:column class="w-full px-5 pt-6 pb-10 gap-4">
                    <native:text font="semibold" class="text-lg text-theme-ink">{{ $quoting ? 'Quote '.$sheetCommit->author->handle() : 'New commit' }}</native:text>

                    @if ($issueCommand)
                        @include('native.partials.issue-badge', ['command' => $issueCommand])
                    @endif

                    <native:bare-text-input
                        ref="sheet-body"
                        class="w-full text-theme-ink"
                        :placeholder="$issueCommand?->placeholder() ?? ($quoting ? 'Add your take' : 'What did you ship?')"
                        native:model.debounce.150ms="sheetBody"
                        autofocus
                        multiline
                        :max-lines="8"
                        :max-length="\App\Devnull\Data\Commit::MaxLength"
                        size="lg"
                        a11y-label="Your commit"
                    />

                    @if ($quoting)
                        @include('native.partials.quoted-commit', ['quoted' => $sheetCommit, 'static' => true])
                    @elseif (! $issueCommand)
                        @include('native.partials.issue-suggestions', [
                            'suggestions' => $sheetTyped ? \App\Devnull\IssueCommand::suggested($sheetBody) : [],
                            'hint' => $issueHintsDismissed ? null : \App\Devnull\IssueCommand::hinted($sheetBody),
                        ])
                    @endif

                    @if ($sheetError)
                        <native:text ref="sheet-error" class="w-full text-sm text-theme-destructive">{{ $sheetError }}</native:text>
                    @endif

                    <native:row class="w-full gap-3 items-center justify-end">
                        @if ($sheetRestored)
                            @include('native.partials.stash-label', ['ref' => 'sheet-stash', 'drop' => 'dropSheetStash'])
                            <native:spacer />
                        @endif
                        @include('native.partials.body-counter', ['body' => $sheetBody, 'ref' => 'sheet-budget'])
                        @if ($issueCommand)
                            {{-- The label stays put while it spins, so the button keeps its size. --}}
                            <native:button ref="sheet-open-issue" :variant="$myColour" :loading="$openingIssue" :disabled="$openingIssue || $sheetLength < \App\Devnull\Data\Commit::MinLength || $sheetLength > \App\Devnull\Data\Commit::MaxLength" @press="openIssueFromSheet">Open issue</native:button>
                        @else
                            <native:button ref="sheet-commit" :variant="$myColour" :disabled="$sheetLength < \App\Devnull\Data\Commit::MinLength || $sheetLength > \App\Devnull\Data\Commit::MaxLength" @press="commitFromSheet">Commit</native:button>
                        @endif
                    </native:row>
                </native:column>
            </native:scroll-view>
        @elseif ($sheetView === 'report')
            <native:scroll-view ref="report-form" class="w-full flex-1">
                <native:column class="w-full px-5 pt-6 pb-10 gap-5">
                    <native:column class="w-full gap-2">
                        <native:text font="semibold" class="text-lg text-theme-ink">Report commit</native:text>
                        <native:text class="text-sm leading-relaxed text-theme-ink-muted">Your report is private. Only moderators can review it. Reporting does not hide this commit.</native:text>
                    </native:column>

                    <native:column class="w-full">
                        <native:column class="w-full h-[1] bg-theme-hairline" />
                        <native:text ref="report-body" class="w-full py-3 text-sm leading-relaxed text-theme-ink" :max-lines="3">{{ $sheetCommit->plainText() }}</native:text>
                        <native:column class="w-full h-[1] bg-theme-hairline" />
                    </native:column>

                    <native:radio-group ref="report-reason" label="Why are you reporting this commit?" native:model="reportReason">
                        @foreach (\App\Devnull\ReportReason::cases() as $reportOption)
                            <native:radio value="{{ $reportOption->value }}" label="{{ $reportOption->label() }}" />
                        @endforeach
                    </native:radio-group>

                    <native:outlined-text-input
                        ref="report-details"
                        class="w-full"
                        label="Details (optional)"
                        supporting="Add context for moderators. Up to {{ \App\Devnull\ReportReason::DetailsMaxLength }} characters."
                        native:model.debounce.150ms="reportDetails"
                        multiline
                        :min-lines="3"
                        :max-lines="6"
                        :max-length="\App\Devnull\ReportReason::DetailsMaxLength"
                    />

                    @if ($sheetError)
                        <native:text ref="sheet-error" class="w-full text-sm text-theme-destructive">{{ $sheetError }}</native:text>
                    @endif

                    <native:button ref="send-report" :variant="$myColour" size="lg" :disabled="$reportReason === ''" @press="sendReport" class="w-full">Send report</native:button>
                </native:column>
            </native:scroll-view>
        @elseif ($sheetView === 'reported')
            <native:column ref="report-received" class="w-full px-5 pt-6 pb-6 gap-5">
                <native:row class="w-full gap-3 items-center">
                    <native:icon :ios="\App\Icons\Lucide::Check->ios()" :android="\App\Icons\Lucide::Check->android()" :size="20" class="text-theme-primary" />
                    <native:text class="flex-1 text-base text-theme-ink">Report received. Moderators will review it privately.</native:text>
                </native:row>
                <native:button ref="report-done" variant="secondary" size="lg" @press="hideCommitSheet" class="w-full">Done</native:button>
            </native:column>
        @elseif ($sheetView === 'issued')
            <native:column ref="issue-opened" class="w-full px-5 pt-6 pb-6 gap-5">
                <native:row class="w-full gap-3 items-center">
                    <native:icon :ios="\App\Icons\Lucide::Check->ios()" :android="\App\Icons\Lucide::Check->android()" :size="20" class="text-theme-primary" />
                    <native:text class="flex-1 text-base text-theme-ink">Opened issue #{{ $openedIssueNumber }}.</native:text>
                </native:row>
                <native:column class="w-full gap-3">
                    @if ($openedIssueUrl)
                        <native:button ref="view-issue" :variant="$myColour" size="lg" :ios-icon-trailing="\App\Icons\Lucide::ArrowUpRight->ios()" :android-icon-trailing="\App\Icons\Lucide::ArrowUpRight->android()" @press="viewOpenedIssue" class="w-full">View issue</native:button>
                    @endif
                    <native:button ref="issue-done" variant="secondary" size="lg" @press="hideCommitSheet" class="w-full">Done</native:button>
                </native:column>
            </native:column>
        @endif
    @endif
</native:bottom-sheet>
