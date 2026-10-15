{{--
    A commit on the graph rail, laid out like the web's commit card: [rail | name, handle and time, body, media, actions].
    The author's avatar is the node on the rail. A commit carrying on its author's run ($continuesRun) gets a lane dot
    instead. The segment above the node takes the previous author's lane, the one below takes this author's.
    $focused draws the commit a page is about: a larger body, its git date, and no link to itself.
--}}
@php($previousLane ??= null)
@php($continuesRun ??= false)
@php($focused ??= false)
@if ($commit->isDeleted())
    @include('native.partials.commit-tombstone')
@elseif ($focused)
    <native:row ref="focused-commit" class="w-full bg-theme-surface">
        @include('native.partials.commit-row-content')
    </native:row>
@else
    <native:pressable ref="commit-{{ $commit->id }}" @press="openCommit('{{ $commit->id }}')" class="w-full bg-theme-surface" :press-opacity="0.7">
        <native:row class="w-full">
            @include('native.partials.commit-row-content')
        </native:row>
    </native:pressable>
@endif
