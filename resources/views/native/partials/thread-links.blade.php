{{--
    The lines beside a reply in a feed's thread (see thread-reply), drawn last in its row so they sit on top: the
    commit's rail running on past, the elbow or the link from the reply above, and the line on down to the next one.
--}}
@if ($railLane !== null)
    @include('native.partials.rail', ['previous' => $railLane])
@endif

@if ($previousLane === null)
    @include('native.partials.thread-elbow', ['lane' => $lane])
@else
    @include('native.partials.dashed-line', ['position' => 'top-0 h-[6]', 'left' => $avatarLine - 0.5, 'colour' => $segment($previousLane), 'dashes' => 1])
@endif

@if ($hasNext)
    @include('native.partials.dashed-line', ['position' => 'top-[38] bottom-0', 'left' => $avatarLine - 0.5, 'colour' => $segment($reply->lane()), 'dashes' => \App\Devnull\Display::dashesFor($reply)])
@endif
