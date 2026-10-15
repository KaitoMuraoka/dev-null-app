<?php

arch()->preset()->php();

arch('native components are SuperNative screens')
    ->expect('App\NativeComponents')
    ->toExtend('Native\Mobile\Edge\NativeComponent')
    ->ignoring(['App\NativeComponents\Layouts', 'App\NativeComponents\Concerns']);

arch('native components render themselves')
    ->expect('App\NativeComponents')
    ->toHaveMethod('render')
    ->ignoring(['App\NativeComponents\Layouts', 'App\NativeComponents\Concerns']);

arch('layouts describe chrome only')
    ->expect('App\NativeComponents\Layouts')
    ->toExtend('Native\Mobile\Edge\Layouts\NativeLayout');

arch('screen concerns are traits')
    ->expect('App\NativeComponents\Concerns')
    ->toBeTraits();
