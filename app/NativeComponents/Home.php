<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Home extends NativeComponent
{
    public function render(): View
    {
        return view('native.home', [
            'devnullUrl' => config('devnull.url'),
        ]);
    }
}
