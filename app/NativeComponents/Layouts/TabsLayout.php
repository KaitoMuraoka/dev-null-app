<?php

namespace App\NativeComponents\Layouts;

use App\Devnull\Account;
use App\Icons\Lucide;
use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

/**
 * The three top-level sections: Log, Notifications and your Profile, with the
 * icons of the web's phone nav. Notifications wears a badge with the unread
 * count devnull last gave.
 */
class TabsLayout extends NativeLayout
{
    /**
     * Each tab's URI. Commits and profiles open beneath the one they were tapped in.
     */
    public const array Tabs = ['/log', '/notifications', '/profile'];

    protected ?string $font = 'semibold';

    public function usesNativeChrome(): bool
    {
        return true;
    }

    public function navBar(NativeComponent $screen): ?NavBar
    {
        return NavBar::make()
            ->title($screen->navTitle())
            ->displayMode('inline');
    }

    public function tabBar(NativeComponent $screen): ?TabBar
    {
        $notifications = Tab::link('Notifications', '/notifications', ios: Lucide::Bell->ios(), android: Lucide::Bell->android());
        $unread = app(Account::class)->unreadNotifications();

        if ($unread > 0) {
            $notifications->badge($unread > 99 ? '99+' : (string) $unread);
        }

        return TabBar::make()
            ->activeColor((string) theme('primary', '#3b6fe0'))
            ->labelVisibility('labeled')
            ->add(Tab::link('Log', '/log', ios: Lucide::GitCommitVertical->ios(), android: Lucide::GitCommitVertical->android()))
            ->add($notifications)
            ->add(Tab::link('Profile', '/profile', ios: Lucide::UserRound->ios(), android: Lucide::UserRound->android()));
    }
}
