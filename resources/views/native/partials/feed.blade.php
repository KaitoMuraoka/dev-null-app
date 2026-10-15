{{--
    A feed's rows from App\Devnull\Timeline: day markers, the last-visit marker and commits on one rail. A commit
    whose thread is open has its replies under it, inside its row's hairline, as on the web.
--}}
@foreach ($rows as $row)
    @if ($row->isDayMarker())
        @include('native.partials.day-marker', ['label' => $row->label, 'lane' => $row->previousLane])
    @elseif ($row->isLastVisitMarker())
        @include('native.partials.last-visit-marker', ['lane' => $row->previousLane])
    @else
        @php($thread = $threads[$row->commit->id] ?? null)
        @include('native.partials.commit-row', ['commit' => $row->commit, 'previousLane' => $row->previousLane, 'continuesRun' => $row->continuesRun, 'hairline' => $thread === null])
        @foreach ($thread ?? [] as $threadReply)
            @include('native.partials.thread-reply', ['reply' => $threadReply, 'lane' => $row->commit->lane(), 'last' => $loop->last])
        @endforeach
    @endif
@endforeach
