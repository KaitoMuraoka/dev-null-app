<?php

use App\NativeComponents\CommitDetail;
use App\NativeComponents\Compose;
use App\NativeComponents\Launch;
use App\NativeComponents\Layouts\StackLayout;
use App\NativeComponents\Layouts\TabsLayout;
use App\NativeComponents\Log;
use App\NativeComponents\Me;
use App\NativeComponents\Notifications;
use App\NativeComponents\Onboarding;
use App\NativeComponents\Profile;
use Illuminate\Support\Facades\Route;

Route::native('/', Launch::class);
Route::native('/onboarding', Onboarding::class);

Route::nativeGroup(TabsLayout::class, function (): void {
    Route::native('/log', Log::class);
    Route::native('/notifications', Notifications::class);
    Route::native('/me', Me::class);
});

Route::nativeGroup(StackLayout::class, function (): void {
    Route::native('/compose', Compose::class);
    Route::native('/commits/{id}', CommitDetail::class);
    Route::native('/users/{username}', Profile::class);
});
