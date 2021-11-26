<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Property;
use App\Models\User;

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
                    $u->user_id = User::find($u->user_id)->first;
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
            $meeting->user_id = User::find($meeting->user_id)->first;
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
}
