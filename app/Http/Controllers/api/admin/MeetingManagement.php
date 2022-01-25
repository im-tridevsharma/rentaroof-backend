<?php

namespace App\Http\Controllers\api\admin;

use App\Events\NotificationSent;
use App\Http\Controllers\Controller;
use App\Models\IboNotification;
use App\Models\Meeting;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class MeetingManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $meetings = Meeting::orderBy("id", "desc")->get();
        if ($meetings) {
            return response([
                'status'    => true,
                'message'   => 'Meetings fetched successfully.',
                'data'      => $meetings->map(function ($u) {
                    $u->property_id = Property::find($u->property_id) ? Property::find($u->property_id)->property_code : 0;
                    $u->user_id = User::find($u->user_id) ? User::find($u->user_id)->first : '-';
                    return $u;
                })
            ], 200);
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
        $meeting = Meeting::find($id);
        if ($meeting) {
            $meeting->property_id = Property::find($meeting->property_id)->property_code;
            $meeting->user_id = User::find($meeting->user_id) ? User::find($meeting->user_id)->first : '-';
            $meeting->meeting_history = json_decode($meeting->meeting_history);
            return response([
                'status'    => true,
                'message'   => 'Meeting fetched successfully.',
                'data'      => $meeting
            ], 200);
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
        $meeting = Meeting::find($id);
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

    //assign_to_ibo
    public function assign_to_ibo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meeting_id'    => 'required',
            'ibo_id'        => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $user = JWTAuth::user();
        $ibo = User::find($request->ibo_id);
        $meeting = Meeting::find($request->meeting_id);

        if ($meeting) {
            $meeting_history = json_decode($meeting->meeting_history);
            $mhid = count($meeting_history);
            array_push($meeting_history, [
                "id"      => $mhid + 1,
                "action"  => "Assigned",
                "action_by" => $user->id,
                "name" => $user->first . ' ' . $user->last,
                "message" => $user->first . "(" . $user->role . ")" . " has assigned this meeting to " . $ibo->first
            ]);
            $meeting->meeting_history = json_encode($meeting_history);
            $meeting->user_id = $request->ibo_id;
            $meeting->start_time = date('Y-m-d H:i:s', strtotime('tomorrow'));
            $meeting->meeting_status = "pending";
            $meeting->save();

            $property = Property::find($meeting->property_id);
            //send notification to ibo
            $ibo_notify = new IboNotification;
            $ibo_notify->ibo_id = $meeting->user_id;
            $ibo_notify->type = 'Urgent';
            $ibo_notify->title = 'Appointment Assigned.';
            $ibo_notify->content = 'Appointment for property - ' . $property->property_code . ' has been assigned by Admin - ' . $user->first . ' ' . $user->last . ' to you.';
            $ibo_notify->name = 'Rent A Roof';
            $ibo_notify->redirect = '/ibo/appointment';

            $ibo_notify->save();

            event(new NotificationSent($ibo_notify));

            //if payment slipt is enabled store it
            if ($request->has('payment_split') && $request->payment_split === 'yes') {
                DB::table('payment_splits')->insert([
                    'property_id'   => $property->id,
                    'ibo_id'        => $request->ibo_id,
                ]);
            }

            return response([
                'status'    => true,
                'message'   => 'Successfully Assigned.',
                'data'      => $meeting
            ], 404);
        }

        return response([
            'status'    => false,
            'message'   => 'Meeting not found!'
        ], 404);
    }
}
