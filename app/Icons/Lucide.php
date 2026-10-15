<?php

namespace App\Icons;

use Native\Mobile\Icon\AndroidSymbol;

/**
 * The Lucide icons the web draws, mapped to the closest SF Symbol and Material icon.
 *
 * EDGE can't show Lucide's own glyphs: an iOS tab is `Tab(_:systemImage:value:)`
 * and `<native:icon>` is `Image(systemName:)`, so both only take SF Symbols, and
 * Android only draws names from the Material Icons font. Outline glyphs use the
 * Outlined Material font to match Lucide's strokes; `$filled` is the on state of
 * a star or a bookmark.
 *
 * iOS resolves dotless names through IconHelper.swift's `getIconForName()`,
 * which turns "star" and "bookmark" into their `.fill` glyphs. The outline
 * bookmark is reachable through its "bookmark_border" alias; the outline star
 * has no alias, so the action row draws it as the ☆ glyph on iOS.
 */
enum Lucide
{
    /** SF Symbols has no vertical commit, so this is Lucide's GitCommitHorizontal drawing. */
    case GitCommitVertical;
    case Bell;
    case UserRound;
    /** Two sliders on the web. The nearest system glyphs are three sliders. */
    case Settings2;
    /** A square speech bubble with a reply arrow inside. Both systems only have the bubble. */
    case MessageSquareReply;
    case Link;
    case Star;
    case Bookmark;
    case CornerDownRight;
    case ChevronRight;
    case ChevronDown;
    case ArrowUpRight;
    case Pin;
    case Trash2;

    public function ios(bool $filled = false): Ios|string
    {
        return match ($this) {
            self::GitCommitVertical => Ios::CircleAndLineHorizontal,
            self::Bell => Ios::Bell,
            self::UserRound => Ios::Person,
            self::Settings2 => Ios::SliderHorizontal3,
            self::MessageSquareReply => Ios::BubbleLeft,
            self::Link => Ios::Link,
            self::Star => $filled ? Ios::StarFill : Ios::Star,
            self::Bookmark => $filled ? Ios::BookmarkFill : 'bookmark_border',
            self::CornerDownRight => Ios::ArrowTurnDownRight,
            self::ChevronRight => Ios::ChevronRight,
            self::ChevronDown => Ios::ChevronDown,
            self::ArrowUpRight => Ios::ArrowUpRight,
            self::Pin => Ios::Pin,
            self::Trash2 => Ios::Trash,
        };
    }

    public function android(bool $filled = false): AndroidSymbol
    {
        return match ($this) {
            self::GitCommitVertical => AndroidOutlined::Commit,
            self::Bell => AndroidOutlined::Notifications,
            self::UserRound => AndroidOutlined::Person,
            self::Settings2 => AndroidOutlined::Tune,
            self::MessageSquareReply => AndroidOutlined::ChatBubble,
            self::Link => AndroidOutlined::Link,
            self::Star => $filled ? Android::Star : AndroidOutlined::Star,
            self::Bookmark => $filled ? Android::Bookmark : AndroidOutlined::Bookmark,
            self::CornerDownRight => AndroidOutlined::SubdirectoryArrowRight,
            self::ChevronRight => AndroidOutlined::ChevronRight,
            self::ChevronDown => AndroidOutlined::ExpandMore,
            self::ArrowUpRight => AndroidOutlined::ArrowOutward,
            self::Pin => AndroidOutlined::PushPin,
            self::Trash2 => AndroidOutlined::Delete,
        };
    }
}
