<?php

namespace App\NativeComponents;

use App\Devnull\Account;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * The first screen: straight to the log for a verified device, otherwise onboarding.
 */
class Launch extends NativeComponent
{
    public function mount(Account $account): void
    {
        $this->replace($account->user() === null ? '/onboarding' : '/log');
    }

    public function render(): View
    {
        return view('native.launch');
    }
}
