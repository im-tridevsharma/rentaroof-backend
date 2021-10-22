<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\LandlordNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class LandlordNotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = JWTAuth::user();
        if ($user && $user->role === 'landlord') {
            $notifications = LandlordNotification::where("landlord_id", $user->id)->orderBy("created_at", "desc")->get();
            return response([
                'status'    => true,
                'message'   => 'Notifications fetched successfully.',
                'data'      => $notifications
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Action not allowed!'
        ], 401);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = JWTAuth::user();
        $validator = Validator::make($request->all(), [
            'landlord_id'    => 'required',
            'type'      => 'required',
            'title'     => 'required|string|max:100',
            'content'   => 'required|string|max:250'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $notification = new LandlordNotification;
        $notification->landlord_id = $request->landlord_id;
        $notification->type = $request->type;
        $notification->title = $request->title;
        $notification->content = $request->content;

        $notification->user_id = $user ? $user->id : NULL;
        $notification->name = $request->has('name') ? $request->name : ($user ? $user->first . ' ' . $user->last : '');
        $notification->email = $request->has('email') ? $request->email : ($user ? $user->email : '');
        $notification->mobile = $request->has('mobile') ? $request->mobile : ($user ? $user->mobile : '');

        $notification->redirect = $request->has('redirect') ? $request->redirect : '';

        if ($notification->save()) {
            return response([
                'status'    => true,
                'message'   => 'Notification saved successfully.',
                'data'      => $notification
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = JWTAuth::user();
        $notification = LandlordNotification::where("landlord_id", $user ? $user->id : 0)->where("id", $id)->first();
        if ($notification) {
            return response([
                'status'    => true,
                'message'   => 'Notification fetched succesfully.',
                'data'      => $notification
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Notification not found!'
        ], 404);
    }

    //mark seen
    public function seen($id)
    {
        $user = JWTAuth::user();
        $notification = LandlordNotification::where("landlord_id", $user ? $user->id : 0)->where("id", $id)->first();
        if ($notification) {
            $notification->is_seen = 1;
            $notification->save();
            return response([
                'status'    => true,
                'message'   => 'Notification seen succesfully.',
                'data'      => $notification
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Notification not found!'
        ], 404);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = JWTAuth::user();
        $notification = LandlordNotification::where("landlord_id", $user ? $user->id : 0)->where("id", $id)->first();
        if ($notification) {
            $notification->delete();
            return response([
                'status'    => true,
                'message'   => 'Notification deleted succesfully.',
                'data'      => $notification
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Notification not found!'
        ], 404);
    }
}