<?php

namespace App\NativeComponents\Concerns;

use App\NativeComponents\Layouts\TabsLayout;

/**
 * Opens commits and profiles inside the tab they were tapped in.
 *
 * Native tabs give each tab its own navigation stack, but only push a URI
 * that starts with the tab's own (PerTabNavigationCoordinator.swift). So
 * /log/commits/{id} slides in from the right with the system back gesture,
 * where /commits/{id} would swap the whole tabs screen for a stack screen
 * with no animation.
 */
trait OpensInTab
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function openInTab(string $path, array $data = []): void
    {
        $this->navigate($this->tabRoot().$path, $data);
    }

    /**
     * The tab this screen sits in: the first segment of its URI, or the log.
     */
    protected function tabRoot(): string
    {
        $root = '/'.strtok(ltrim($this->nativeRouter?->currentUri() ?? '', '/'), '/');

        return in_array($root, TabsLayout::Tabs, true) ? $root : TabsLayout::Tabs[0];
    }
}
