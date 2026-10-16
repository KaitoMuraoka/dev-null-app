{{--
    A commit's body, drawn from its segments as the web draws it: paragraphs of prose with tappable mentions and
    inline code on a wash, and fenced code blocks as mono text in a hairline box.
    The runs inside a paragraph sit flush left with no blank lines between them on purpose: under whitespace-pre-wrap
    any whitespace between those tags would print. Each line ends in a tag or a directive, whose newline PHP drops.

    A body too long to show whole in a list shows its start, as devnull cut it, and "Show more" in Commit Blue, which
    opens the rest where it is. The commit a page is about ($focused) is always whole.
--}}
@php($size ??= 'text-[15px]')
@php($folded = ! ($focused ?? false) && $commit->isFolded() && ! in_array($commit->id, $unfolded ?? [], true))
@foreach ($folded ? $commit->previewContent : $commit->content as $block)
    @if ($block->isCode())
        <native:column class="w-full mt-1 mb-1 rounded-md border border-theme-hairline bg-theme-wash">
            @if ($block->language)
                <native:text font="mono" class="w-full px-3 pt-2 text-[11px] text-right text-theme-ink-muted">{{ $block->language }}</native:text>
            @endif
            <native:text :text="$block->code" font="mono" class="w-full px-3 py-2 text-[13px] leading-snug text-theme-ink whitespace-pre-wrap select-text" />
        </native:column>
    @else
<native:text class="w-full {{ $size }} leading-relaxed text-theme-ink whitespace-pre-wrap">@foreach ($block->runs as $run)
@if ($run->isMention())
<native:text :text="$run->text" font="semibold" class="font-semibold text-theme-primary" @press="openProfile('{{ $run->username }}')" />
@elseif ($run->isCode)
<native:text :text="$run->text" font="mono" class="text-[13px] bg-theme-wash" />
@else
<native:text :text="$run->text" />
@endif
@endforeach
</native:text>
    @endif
@endforeach
@if ($folded)
    <native:pressable ref="show-more-{{ $commit->id }}" @press="showMore('{{ $commit->id }}')" a11y-label="Show more">
        <native:text font="medium" class="{{ $size }} text-theme-primary">Show more</native:text>
    </native:pressable>
@endif
