<?php

use Illuminate\Support\Facades\Broadcast;

// Each user listens only to their own private channel (the bell's live notifications).
Broadcast::channel('App.Models.User.{id}', function ($user, string $id) {
    return (string) $user->id === $id;
});
