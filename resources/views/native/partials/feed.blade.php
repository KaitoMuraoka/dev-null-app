{{--
    A feed's rows from App\Devnull\Timeline: day markers, the last-visit marker and commits on the rail. Under a commit
    hangs its thread, inside its row's hairline, as on the web: the newest reply devnull previews, with "Show more"
    when there are others, or the replies opened in place. The commit's rail runs on past them when its author's run
    goes on below.
--}}
@foreach ($rows as $row)
    @if ($row->isDayMarker())
        @include('native.partials.day-marker', ['label' => $row->label, 'lane' => $row->previousLane])
    @elseif ($row->isLastVisitMarker())
        @include('native.partials.last-visit-marker', ['lane' => $row->previousLane])
    @else
        @php($feedCommit = $row->commit)
        @php($opened = $threads[$feedCommit->id] ?? null)
        @php($threadReplies = $opened ?? ($feedCommit->isDeleted() ? [] : $feedCommit->replyPreview?->replies ?? []))
        @php($hasMoreReplies = $opened === null && ! $feedCommit->isDeleted() && ($feedCommit->replyPreview?->hasMore ?? false))
        @php($railLane = $row->continuesNext ? $feedCommit->lane() : null)
        @include('native.partials.commit-row', [
            'commit' => $feedCommit,
            'previousLane' => $row->previousLane,
            'continuesRun' => $row->continuesRun,
            'continuesNext' => $row->continuesNext,
            'threadBelow' => $threadReplies !== [],
        ])
        @foreach ($threadReplies as $index => $threadReply)
            @include('native.partials.thread-reply', [
                'reply' => $threadReply,
                'lane' => $feedCommit->lane(),
                'previousLane' => $index === 0 ? null : $threadReplies[$index - 1]->lane(),
                'hasNext' => ! $loop->last || $hasMoreReplies,
                'railLane' => $railLane,
                'last' => $loop->last && ! $hasMoreReplies,
            ])
        @endforeach
        @if ($hasMoreReplies && $threadReplies !== [])
            @include('native.partials.thread-more', ['commit' => $feedCommit, 'previousLane' => end($threadReplies)->lane(), 'railLane' => $railLane])
        @endif
    @endif
@endforeach
