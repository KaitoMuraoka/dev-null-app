{{-- A feed's rows from App\Devnull\Timeline: day markers, the last-visit marker and commits on one rail. --}}
@foreach ($rows as $row)
    @if ($row->isDayMarker())
        @include('native.partials.day-marker', ['label' => $row->label, 'lane' => $row->previousLane])
    @elseif ($row->isLastVisitMarker())
        @include('native.partials.last-visit-marker', ['lane' => $row->previousLane])
    @else
        @include('native.partials.commit-row', ['commit' => $row->commit, 'previousLane' => $row->previousLane, 'continuesRun' => $row->continuesRun])
    @endif
@endforeach
