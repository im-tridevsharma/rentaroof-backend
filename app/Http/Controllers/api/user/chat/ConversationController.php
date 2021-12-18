<?php

namespace App\Http\Controllers\api\user\chat;

use App\Events\ConversationCreated;
use App\Events\MessageSentEvent;
use App\Events\NotificationSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\IboNotification;
use App\Models\LandlordNotification;
use App\Models\Property;
use App\Models\PropertyDeal;
use App\Models\TenantNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                $receiver = User::find($c->receiver_id)->only(['first', 'last', 'profile_pic', 'is_logged_in', 'id', 'role']);
                $sender = User::find($c->sender_id)->only(['first', 'last', 'profile_pic', 'is_logged_in', 'id', 'role']);
                $last_message = ChatMessage::select("id", "conversation_id", "message_type", "message")->where("conversation_id", $c->id)->orderBy('created_at', 'desc')->first();
                $c->receiver = $receiver;
                $c->sender = $sender;
                if ($last_message) {
                    $c->last_message = $last_message;
                } else {
                    $c->last_message = null;
                }

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

    //change_status
    public function change_status(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'conversation_id' => 'required',
            'status'          => 'required|in:yes,no,pending'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $conversation = Conversation::find($request->conversation_id);
        if ($conversation) {
            $conversation->is_accepted = $request->status;
            $conversation->save();

            //send notification to sender
            $sender = User::find($conversation->sender_id);
            $receiver = User::find($conversation->receiver_id);

            if ($sender) {
                //notify user meeting is scheduled
                $notify = $sender->role === 'tenant' ? new TenantNotification : ($sender->role === 'ibo' ? new IboNotification : new LandlordNotification);
                if ($sender->role === 'tenant') {
                    $notify->tenant_id = $sender->id;
                }
                if ($sender->role === 'ibo') {
                    $notify->ibo_id = $sender->id;
                }
                if ($sender->role === 'landlord') {
                    $notify->landlord_id = $sender->id;
                }

                $changed = $request->status == 'yes' ? 'accepted' : 'rejected';

                $notify->type = 'Urgent';
                $notify->title = 'Chat Request Status';
                $notify->content = $receiver->first . ' ' . $receiver->last . ' has ' . $changed . ' your chat request.';
                $notify->name = 'Rent A Roof';
                $notify->redirect = '/' . $sender->role . '/chat#' . $conversation->id;

                $notify->save();

                event(new NotificationSent($notify));
            }

            return response([
                'status'    => true,
                'message'   => 'Status changed successfully.',
                'data'      => $conversation
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Conversation not found.'
        ], 404);
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
            $receiver = User::find($conversation->receiver_id)->only(['first', 'last', 'role', 'profile_pic', 'is_logged_in', 'id']);
            $sender = User::find($conversation->sender_id)->only(['first', 'last', 'role', 'profile_pic', 'is_logged_in', 'id']);
            $last_message = ChatMessage::where("conversation_id", $conversation->id)->orderBy('created_at', 'desc')->first();

            event(new ConversationCreated($conversation, $receiver, $sender, $last_message));

            //notification for chat start
            if ($receiver['role'] === 'tenant') {
                $user_notify = new TenantNotification;
                $user_notify->tenant_id = $request->receiver_id;
                $user_notify->title = 'New Chat Request';
                $user_notify->content = $sender['first'] . ' ' . $sender['last'] . ' wants to chat with you. Please visit chat screen!';
                $user_notify->type = 'Urgent';
                $user_notify->name = 'Rent A Roof';
                $user_notify->redirect = '/tenant/chat#' . $conversation->id;

                $user_notify->save();
                event(new NotificationSent($user_notify));
            }

            //notification for chat start
            if ($receiver['role'] === 'ibo') {
                $ibo_notify = new IboNotification;
                $ibo_notify->ibo_id = $request->receiver_id;
                $ibo_notify->title = 'New Chat Request';
                $ibo_notify->content = $sender['first'] . ' ' . $sender['last'] . ' wants to chat with you. Please visit chat screen!';
                $ibo_notify->type = 'Urgent';
                $ibo_notify->name = 'Rent A Roof';
                $ibo_notify->redirect = '/ibo/chat#' . $conversation->id;

                $ibo_notify->save();
                event(new NotificationSent($ibo_notify));
            }

            //notification for chat start
            if ($receiver['role'] === 'landlord') {
                $landlord_notify = new LandlordNotification;
                $landlord_notify->landlord_id = $request->receiver_id;
                $landlord_notify->title = 'New Chat Request';
                $landlord_notify->content = $sender['first'] . ' ' . $sender['last'] . ' wants to chat with you. Please visit chat screen!';
                $landlord_notify->type = 'Urgent';
                $landlord_notify->name = 'Rent A Roof';
                $landlord_notify->redirect = '/landlord/chat#' . $conversation->id;

                $landlord_notify->save();
                event(new NotificationSent($landlord_notify));
            }

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
            $messages = ChatMessage::where("conversation_id", $conversationId)->get()->map(function ($m) {
                if ($m->message_type === 'deal') {
                    $m->deal = PropertyDeal::find($m->deal_id);
                    $m->deal->property = Property::where("id", $m->deal->property_id)->first(['name', 'property_code', 'front_image', 'monthly_rent', 'posted_by']);
                }
                return $m;
            })->groupBy(function ($item) {
                return $item->date;
            });
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

    //get message
    public function getMessagesForMobile($conversationId)
    {
        if ($conversationId) {
            $messages = ChatMessage::where("conversation_id", $conversationId)->get()->map(function ($m) {
                if ($m->message_type === 'deal') {
                    $m->deal = PropertyDeal::find($m->deal_id);
                    $m->deal->property = Property::where("id", $m->deal->property_id)->first(['name', 'property_code', 'front_image', 'monthly_rent', 'posted_by']);
                }
                return $m;
            })->groupBy(function ($item) {
                return $item->date;
            });

            $_messages = [];
            foreach ($messages as $key => $message) {
                $data = [
                    "date" => $key,
                    "message" => $message
                ];

                array_push($_messages, $data);
            }

            return response([
                'status'    => true,
                'message'   => 'Messages fetched successfully.',
                'data'      => $_messages
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

            $deal = '';
            $property = '';

            //if message is  a deal
            if ($request->message_type === 'deal') {

                $deal = new PropertyDeal;
                $deal->property_id = $request->property_id;
                $deal->created_by = $request->created_by;
                $deal->offer_for = $request->receiver_id;
                $deal->offer_price = $request->offer_price;
                $deal->conversationId = $conversation->id;
                $deal->offer_expires_time = date("Y-m-d H:i:s", strtotime($request->offer_expires_date . ' ' . $request->offer_expires_time));

                $deal->save();

                $message->deal_id = $deal->id;
                $message->save();
            }

            if ($request->filled('property_id')) {
                $property = DB::table('properties')->where('id', $request->property_id)->first(['name', 'property_code', 'front_image', 'monthly_rent', 'posted_by']);
            }

            event(new MessageSentEvent($message, $deal, $property));

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
