<?php

namespace App\Icons;

use Native\Mobile\Icon\AndroidSymbol;
use Native\Mobile\Icon\Icon;

/**
 * The Lucide icons the web draws.
 *
 * Most are Lucide's own drawings. Their SVGs, from lucide-react 1.57.0, are in
 * resources/icons, and the NativePHP build turns each one into a template image
 * on iOS and a VectorDrawable on Android, so both platforms draw the web's
 * strokes, tinted like any other icon. `$filled` is the on state of a star or
 * a bookmark, which the web draws as the same shape with `fill-current`.
 *
 * The rest still map to the closest SF Symbol and outlined Material icon. To
 * draw one of them as the web does, add its SVG to resources/icons and name it
 * in custom().
 */
enum Lucide
{
    case GitCommitVertical;
    case Bell;
    case UserRound;
    case Settings2;
    case MessageSquareReply;
    case Link;
    case Star;
    case Bookmark;
    case Trash2;
    case CornerDownRight;
    case ChevronRight;
    case ChevronDown;
    case ArrowUpRight;
    case ArrowUp;
    case Pin;
    case ListOrdered;
    case Building2;
    case MapPin;
    case CalendarDays;
    /** The web draws GitHub's own mark, which neither system has, so this is the code glyph. */
    case Github;

    /**
     * The `custom:<name>` icon drawn from resources/icons/<name>.svg, or null for a system glyph.
     */
    public function custom(bool $filled = false): ?string
    {
        $name = match ($this) {
            self::GitCommitVertical => 'git-commit-vertical',
            self::Bell => 'bell',
            self::UserRound => 'user-round',
            self::Settings2 => 'settings-2',
            self::MessageSquareReply => 'message-square-reply',
            self::Link => 'link',
            self::Star => $filled ? 'star-filled' : 'star',
            self::Bookmark => $filled ? 'bookmark-filled' : 'bookmark',
            self::Trash2 => 'trash-2',
            self::ArrowUp => 'arrow-up',
            default => null,
        };

        return $name === null ? null : Icon::custom($name);
    }

    public function ios(bool $filled = false): Ios|string
    {
        return $this->custom($filled) ?? match ($this) {
            self::CornerDownRight => Ios::ArrowTurnDownRight,
            self::ChevronRight => Ios::ChevronRight,
            self::ChevronDown => Ios::ChevronDown,
            self::ArrowUpRight => Ios::ArrowUpRight,
            self::Pin => Ios::Pin,
            self::ListOrdered => Ios::ListNumber,
            self::Building2 => Ios::Building2,
            self::MapPin => Ios::Mappin,
            self::CalendarDays => Ios::Calendar,
            self::Github => Ios::ChevronLeftForwardslashChevronRight,
        };
    }

    public function android(bool $filled = false): AndroidSymbol|string
    {
        return $this->custom($filled) ?? match ($this) {
            self::CornerDownRight => AndroidOutlined::SubdirectoryArrowRight,
            self::ChevronRight => AndroidOutlined::ChevronRight,
            self::ChevronDown => AndroidOutlined::ExpandMore,
            self::ArrowUpRight => AndroidOutlined::ArrowOutward,
            self::Pin => AndroidOutlined::PushPin,
            self::ListOrdered => AndroidOutlined::FormatListNumbered,
            self::Building2 => AndroidOutlined::Business,
            self::MapPin => AndroidOutlined::LocationOn,
            self::CalendarDays => AndroidOutlined::CalendarToday,
            self::Github => AndroidOutlined::Code,
        };
    }
}
