<?php

namespace App\Http\Controllers\api\user;

use App\Events\NotificationSent;
use App\Http\Controllers\Controller;
use App\Models\IboNotification;
use App\Models\IboRating;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class IboRatingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response([
            'status'    => false,
            'message'   => 'Action not allowed'
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
        $validator = Validator::make($request->all(), [
            'rating' => 'required',
            'review' => 'required|string',
            'ibo_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }
        $user = JWTAuth::user();

        $rating = $user && IboRating::where("user_id", $user->id)->count() > 0 ? IboRating::where("user_id", $user->id)->first() : new IboRating;
        $rating->rating = $request->rating;
        $rating->review = $request->review;
        $rating->ibo_id = $request->ibo_id;
        $rating->user_id = $user ? $user->id : 0;
        $rating->user_role = $user ? $user->role : '';
        $rating->name = $user ? $user->first . ' ' . $user->last : '';
        $rating->email = $user ? $user->email : '';
        $rating->contact = $user ? $user->mobile : '';

        if ($rating->save()) {
            //notify user meeting is scheduled
            $ibo_notify = new IboNotification;
            $ibo_notify->ibo_id = $rating->ibo_id;
            $ibo_notify->type = 'Notification';
            $ibo_notify->title = 'New Review Notification';
            $ibo_notify->content = $user->first . ' ' . $user->last . ' has reviewed you.';
            $ibo_notify->name = 'Rent A Roof';

            $ibo_notify->save();

            event(new NotificationSent($ibo_notify));

            return response([
                'status'    => true,
                'message'   => 'Rating and review saved successfully.',
                'data'      => $rating
            ], 200);
        }

        return response([
            'status'     => false,
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
        $rating = IboRating::find($id);
        if ($rating) {
            return response([
                'status'     => true,
                'message'   => 'Rating fetched successfully.',
                'data'      => $rating
            ], 500);
        }

        return response([
            'status'     => false,
            'message'   => 'Rating not found!'
        ], 404);
    }

    //get all rating of ibo
    public function all($id)
    {
        $ratings = IboRating::where("ibo_id", $id)->orderBy("created_at", "desc")->get()->map(function ($r) {
            $r->user_pic = $r->user_id ? (User::find($r->user_id) ? User::find($r->user_id)->profile_pic : '') : '';
            return $r;
        });
        if ($ratings) {
            return response([
                'status'     => true,
                'message'   => 'Ratings fetched successfully.',
                'data'      => $ratings
            ], 200);
        }

        return response([
            'status'     => false,
            'message'   => 'Ratings not found!'
        ], 404);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required',
            'review' => 'required|string',
            'ibo_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }
        $user = JWTAuth::user();

        if (!$user) {
            return response([
                'status'     => false,
                'message'   => 'Permission not allowed!'
            ], 401);
        }

        $rating = IboRating::where("user_id", $user->id)->where("id", $id)->first();
        if ($rating) {
            $rating->rating = $request->rating;
            $rating->review = $request->review;
            $rating->ibo_id = $request->ibo_id;
            $rating->user_id = $user ? $user->id : 0;
            $rating->user_role = $user ? $user->role : '';
            $rating->name = $user ? $user->first . ' ' . $user->last : '';
            $rating->email = $user ? $user->email : '';
            $rating->contact = $user ? $user->mobile : '';

            if ($rating->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Rating and review updated successfully.',
                    'data'      => $rating
                ], 200);
            }

            return response([
                'status'     => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }

        return response([
            'status'     => false,
            'message'   => 'Rating not found!'
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

        if (!$user) {
            return response([
                'status'     => false,
                'message'   => 'Permission not allowed!'
            ], 404);
        }

        $rating = IboRating::where("user_id", $user->id)->where("id", $id)->first();
        if ($rating) {
            $rating->delete();
            return response([
                'status'     => true,
                'message'   => 'Rating deleted successfully.',
                'data'      => $rating
            ], 500);
        }

        return response([
            'status'     => false,
            'message'   => 'Rating not found!'
        ], 404);
    }
}
