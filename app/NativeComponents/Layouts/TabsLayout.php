<?php

namespace App\NativeComponents\Layouts;

use App\Devnull\Account;
use App\Icons\Android;
use App\Icons\Ios;
use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

/**
 * The three top-level sections: Log, Notifications and Me. Notifications
 * wears a badge with the unread count devnull last gave.
 */
class TabsLayout extends NativeLayout
{
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
        $notifications = Tab::link('Notifications', '/notifications', ios: Ios::Bell, android: Android::Notifications);
        $unread = app(Account::class)->unreadNotifications();

        if ($unread > 0) {
            $notifications->badge($unread > 99 ? '99+' : (string) $unread);
        }

        return TabBar::make()
            ->activeColor((string) theme('primary', '#3b6fe0'))
            ->labelVisibility('labeled')
            ->add(Tab::link('Log', '/log', ios: Ios::CircleAndLineHorizontal, android: Android::Commit))
            ->add($notifications)
            ->add(Tab::link('Me', '/me', ios: Ios::PersonCropCircle, android: Android::AccountCircle));
    }
}
