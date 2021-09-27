<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Meeting;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class MeetingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = JWTAuth::user();
        $meetings = Meeting::where("user_id", $user->id)->orWhere("created_by_id", $user->id)->get();

        if ($meetings) {
            return response([
                'status'    => true,
                'message'   => 'Meetings fetched successfully.',
                'data'      => $meetings
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong.'
        ], 500);
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
            'title'     => 'required|string|max:200',
            'user_id'   => 'required',
            'property_id'   => 'required',
            'start_time'   => 'required|string',
            'end_time_expected'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some error occured',
                'error'     => $validator->errors()
            ], 404);
        }

        //save information
        $meeting = new Meeting;
        $meeting->title = $request->title;
        $meeting->description = isset($request->description) ? $request->description : '';
        $meeting->user_id = $request->user_id;
        $meeting->user_role = User::find($request->user_id)->role;
        $meeting->property_id = $request->property_id;
        $meeting->user_name = User::find($request->user_id)->first . ' ' . User::find($request->user_id)->last;;
        $meeting->user_contact = User::find($request->user_id)->mobile;
        $meeting->start_time = !empty($request->start_time) ? date("Y-m-d H:i:s", strtotime($request->start_time)) : NULL;
        $meeting->end_time_expected = !empty($request->end_time_expected) ? date("Y-m-d H:i:s", strtotime($request->end_time_expected)) : NULL;
        $meeting->created_by_id = $user->id;
        $meeting->created_by_name = $user->first . ' ' . $user->last;
        $meeting->created_by_role = $user->role;
        //create history for meeting
        $meeting_history = [
            [
                "id"      => 1,
                "action"  => "Created",
                "action_by" => $user->id,
                "name" => $user->first . ' ' . $user->last,
                "message" => $user->first . "(" . $user->role . ")" . " has created this meeting."
            ]
        ];
        $meeting->meeting_history = json_encode($meeting_history);

        if ($meeting->save()) {
            return response([
                'status'    => true,
                'message'   => 'New Meeting saved successfully.',
                'data'      => $meeting
            ]);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong.'
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
        $meeting = Meeting::where(function ($q) use ($id, $user) {
            $q->where("id", $id);
            $q->where(function ($q) use ($user) {
                $q->where("user_id", $user->id);
                $q->orWhere("created_by_id", $user->id);
            });
        })->get();

        if ($meeting && count($meeting) > 0) {
            return response([
                'status'    => true,
                'message'   => 'Meeting fetched successfully.',
                'data'      => $meeting[0]
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Meeting not found.'
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
        $user = JWTAuth::user();
        $validator = Validator::make($request->all(), [
            'title'     => 'required|string|max:200',
            'user_id'   => 'required',
            'property_id'   => 'required',
            'start_time'   => 'required|string',
            'end_time_expected'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some error occured',
                'error'     => $validator->errors()
            ], 404);
        }

        //save information
        $meeting = Meeting::where("created_by_id", $user->id)->find($id);
        if ($meeting) {
            $meeting->title = $request->title;
            $meeting->description = isset($request->description) ? $request->description : '';
            $meeting->user_id = $request->user_id;
            $meeting->user_role = User::find($request->user_id)->role;
            $meeting->property_id = $request->property_id;
            $meeting->user_name = User::find($request->user_id)->first . ' ' . User::find($request->user_id)->last;;
            $meeting->user_contact = User::find($request->user_id)->mobile;
            $meeting->start_time = !empty($request->start_time) ? date("Y-m-d H:i:s", strtotime($request->start_time)) : NULL;
            $meeting->end_time_expected = !empty($request->end_time_expected) ? date("Y-m-d H:i:s", strtotime($request->end_time_expected)) : NULL;
            $meeting->created_by_id = $user->id;
            $meeting->created_by_name = $user->first . ' ' . $user->last;
            $meeting->created_by_role = $user->role;
            //create history for meeting
            $meeting_history = json_decode($meeting->meeting_history);
            $mhid = count($meeting_history);
            array_push($meeting_history, [
                "id"      => $mhid + 1,
                "action"  => "Updated",
                "action_by" => $user->id,
                "name" => $user->first . ' ' . $user->last,
                "message" => $user->first . "(" . $user->role . ")" . " has updated this meeting."
            ]);
            $meeting->meeting_history = json_encode($meeting_history);

            if ($meeting->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Meeting updated successfully.',
                    'data'      => $meeting
                ]);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Meeting not found.'
        ], 404);
    }

    /**
     * Update status of meeting
     */

    public function update_status(Request $request, $id)
    {
        $user = JWTAuth::user();
        $validator = Validator::make($request->input(), [
            'status'    => 'required|string|in:pending,cancelled,approved'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some error occured.',
                'error'     => $validator->errors()
            ], 404);
        }

        $meeting = Meeting::where(function ($q) use ($id, $user) {
            $q->where("id", $id);
            $q->where(function ($q) use ($user) {
                $q->where("user_id", $user->id);
                $q->orWhere("created_by_id", $user->id);
            });
        })->get();

        if ($meeting && count($meeting) > 0) {
            $meeting = $meeting[0];
            $meeting->meeting_status = $request->status;
            $meeting_history = json_decode($meeting->meeting_history);
            array_push($meeting_history, [
                "id"      => count($meeting_history) + 1,
                "action"  => "Status Updated",
                "action_by" => $user->id,
                "name" => $user->first . ' ' . $user->last,
                "message" => $user->first . "(" . $user->role . ")" . " has updated meeting's status."
            ]);
            $meeting->meeting_history = json_encode($meeting_history);

            if ($meeting->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Meeting updated successfully.',
                    'data'      => $meeting
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Meeting not found.'
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
        $meeting = Meeting::where("created_by_id", $user->id)->find($id);

        if ($meeting) {
            $meeting->delete();
            return response([
                'status'    => true,
                'message'   => 'Meeting deleted successfully.',
                'data'      => $meeting
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Meeting not found.'
        ], 404);
    }
}
