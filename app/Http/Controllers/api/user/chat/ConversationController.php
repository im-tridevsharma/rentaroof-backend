<?php

namespace App\Http\Controllers\api\user\chat;

use App\Events\ConversationCreated;
use App\Events\MessageSentEvent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ConversationController extends Controller
{
    //all conversation for loggedin user
    public function index()
    {
        $user = JWTAuth::user();
        if ($user) {
            $conversations = Conversation::where("sender_id", $user->id)->orWhere("receiver_id", $user->id)->get()->map(function ($c) {
                $receiver = User::find($c->receiver_id)->only(['first', 'last', 'profile_pic', 'is_logged_in']);
                $sender = User::find($c->sender_id)->only(['first', 'last', 'profile_pic', 'is_logged_in']);
                $last_message = ChatMessage::where("conversation_id", $c->id)->orderBy('created_at', 'desc')->first();
                $c->receiver = $receiver;
                $c->sender = $sender;
                $c->last_message = $last_message;

                return $c;
            });
            return response([
                'status'    => true,
                'message'   => 'Conversation fetched successfully',
                'data'      => $conversations
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Unauthorized!'
        ], 401);
    }



    //create new conversation
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sender_id'     => 'required',
            'receiver_id'   => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured',
                'error'     => $validator->errors()
            ], 400);
        }

        $is = Conversation::where("sender_id", $request->sender_id)
            ->where("receiver_id", $request->receiver_id)->first();

        if ($is) {
            return response([
                'status'    => true,
                'message'   => 'Conversation exists already.',
                'data'      => $is
            ], 200);
        }

        $conversation = new Conversation;
        $conversation->sender_id = $request->sender_id;
        $conversation->receiver_id = $request->receiver_id;

        if ($conversation->save()) {

            $receiver = User::find($conversation->receiver_id)->only(['first', 'last', 'profile_pic', 'is_logged_in']);
            $sender = User::find($conversation->sender_id)->only(['first', 'last', 'profile_pic', 'is_logged_in']);
            $last_message = ChatMessage::where("conversation_id", $conversation->id)->orderBy('created_at', 'desc')->first();
            $conversation->receiver = $receiver;
            $conversation->sender = $sender;
            $conversation->last_message = $last_message;

            event(new ConversationCreated($conversation));
            return response([
                'status'    => true,
                'message'   => 'Conversation created successfully.',
                'data'      => $conversation
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }


    //delete a conversation
    public function destroy($id)
    {
        $conversation = Conversation::find($id);
        if ($conversation) {
            ChatMessage::where("conversation_id", $conversation->id)->delete();
            $conversation->delete();

            return response([
                'status'    =>  true,
                'message'   => 'Conversation deleted successfully.',
                'data'      => $conversation
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Conversation not found!'
        ], 404);
    }




    //get message
    public function getMessages($conversationId)
    {
        if ($conversationId) {
            $messages = ChatMessage::where("conversation_id", $conversationId)->get()->groupBy(function ($item) {
                return $item->date;
            });;
            return response([
                'status'    => true,
                'message'   => 'Messages fetched successfully.',
                'data'      => $messages
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Conversation not found!'
        ], 404);
    }

    //send message 
    public function sendMessage(Request $request)
    {
        if (!$request->filled('message')) {
            return response([
                'status'    => false,
                'message'   => 'Not a valid message.'
            ], 422);
        }
        $conversation = Conversation::find($request->conversation_id);

        if ($conversation) {
            $message = new ChatMessage;
            $message->conversation_id = $conversation->id;
            $message->sender_id = $request->sender_id;
            $message->receiver_id = $request->receiver_id;
            $message->message_type = $request->message_type;
            $message->message = $request->message;
            $message->date = date('Y-m-d');

            $message->save();

            event(new MessageSentEvent($message));

            return response([
                'status'    => true,
                'message'   => 'Message saved successfully',
                'data'      => $message
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Conversation not found.'
        ], 422);
    }

    //users_for_conversation

    public function users_for_conversation()
    {
        $users = User::select("first", "last", 'id', 'role')->where("id", "!=", JWTAuth::user()->id)
            ->where("role", "!=", "admin")->where("account_status", "activated")->get();
        return response([
            'status'    => true,
            'message'   => 'Users fetched for conversation.',
            'data'      => $users
        ], 200);
    }
}
