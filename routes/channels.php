<?php

use App\Models\Target;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// A target's live feed: its owner and anyone it is shared with.
Broadcast::channel('targets.{target}', function (User $user, Target $target) {
    return $target->roleFor($user) !== null;
});
