<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/


Broadcast::channel('notification', function ($user) {
    return $user;
});

Broadcast::channel('admin', function ($user) {
    return $user->role === 'admin' ? true : false;
});


Broadcast::channel('chat-screen', function ($user) {
    return $user;
});

Broadcast::channel('conversation.{id}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);
    return $conversation ? $conversation->sender_id === $user->id || $conversation->receiver_id === $user->id : false;
});
