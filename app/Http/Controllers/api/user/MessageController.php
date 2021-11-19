<?php

namespace App\Http\Controllers\api\user;

use App\Events\NewChatMessage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function broadcast(Request $request)
    {

        if (!$request->filled('message')) {
            return response()->json([
                'message' => 'No message to send'
            ], 422);
        }

        // TODO: Sanitize input

        $event = NewChatMessage::broadcast($request->message, $request->user);

        return response([
            'status'    => true,
            'message'   => 'Message event triggered!',
            'data'      => $event
        ], 200);
    }
}
