{{--
    The graph rail, drawn over a row's 64pt gutter. Place it last inside a row that reserves the gutter, so it draws on top.
    $previous and $tail are a lane number, 'rail' for Rail Gray, or null for no segment. $tail can also be 'staged':
    a dashed Rail Gray line of $tailDashes dashes under a commit that isn't made yet.
    $node is what sits on the rail, centred 32pt down as on the web:
      'avatar'    the author's avatar ringed in their lane ($user, $lane), pressing $press if given
      'staged'    the same with a dashed ring, for a commit not made yet
      'dot'       a lane dot level with the name, for a commit carrying on its author's run
      'root'      a hollow lane dot, the end of history
      'tombstone' a dashed Rail Gray ring where a deleted commit's avatar was
      'skeleton'  a wash disc, for a row still loading
      null        no node; a $previous segment then runs the full height of the row
--}}
@php($previous ??= null)
@php($tail ??= null)
@php($node ??= null)
@php($press ??= null)
@php($tailDashes ??= 3)
@php($center = 32)
@php($segment = fn (int|string $lane): string => $lane === \App\Devnull\Lane::Rail ? 'bg-theme-rail' : 'bg-theme-lane-'.$lane)

@if ($node === null)
    @if ($previous !== null)
        <native:column class="absolute top-0 bottom-0 left-[31] w-[2] {{ $segment($previous) }}" />
    @endif
@else
    @if ($previous !== null)
        <native:column class="absolute top-0 left-[31] w-[2] h-[{{ $center }}] {{ $segment($previous) }}" />
    @endif
    @if ($tail === 'staged')
        <native:column class="absolute top-[58] left-[31] w-[2] gap-[4]">
            @foreach (range(1, $tailDashes) as $dash)
                <native:column class="w-[2] h-[4] bg-theme-rail" />
            @endforeach
        </native:column>
    @elseif ($tail !== null)
        <native:column class="absolute top-[{{ $center }}] bottom-0 left-[31] w-[2] {{ $segment($tail) }}" />
    @endif

    @if ($node === 'dot' || $node === 'root')
        {{-- A continuing commit's dot sits 9pt higher, level with the author's name, as on the web. --}}
        <native:column class="absolute top-[{{ $node === 'dot' ? $center - 18 : $center - 9 }}] left-[23]">
            @if ($press)
                <native:pressable @press="{{ $press }}" a11y-label="{{ $user->name }}'s profile">
                    @include('native.partials.rail-node', ['lane' => $lane])
                </native:pressable>
            @else
                @include('native.partials.rail-node', ['lane' => $lane, 'filled' => $node === 'dot'])
            @endif
        </native:column>
    @else
        <native:column class="absolute top-[{{ $center - 24 }}] left-[8]">
            @if ($node === 'avatar' && $press)
                <native:pressable @press="{{ $press }}" a11y-label="{{ $user->name }}'s profile">
                    @include('native.partials.rail-avatar', ['user' => $user, 'lane' => $lane])
                </native:pressable>
            @elseif ($node === 'avatar' || $node === 'staged')
                @include('native.partials.rail-avatar', ['user' => $user, 'lane' => $lane, 'staged' => $node === 'staged'])
            @elseif ($node === 'tombstone')
                <native:column :width="48" :height="48" class="rounded-full bg-theme-surface">
                    @include('native.partials.dashed-ring', ['size' => 48, 'colour' => 'bg-theme-rail'])
                </native:column>
            @elseif ($node === 'skeleton')
                <native:stack :width="48" :height="48" center class="rounded-full bg-theme-surface">
                    <native:column :width="40" :height="40" class="rounded-full bg-theme-wash" />
                </native:stack>
            @endif
        </native:column>
    @endif
@endif
