<?php

namespace App\Http\Controllers\api\admin;

use App\Events\NotificationSent;
use App\Http\Controllers\Controller;
use App\Models\Complain;
use App\Models\TenantNotification;
use Illuminate\Http\Request;

class ComplainManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $complains = Complain::all();
        return response([
            'status'    => true,
            'message'   => 'Complains fetched successfully.',
            'data'      => $complains
        ]);
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $complain = Complain::find($id);
        if ($complain) {
            return response([
                'status'    => true,
                'message'   => 'Complain fetched successfully.',
                'data'      => $complain
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Complain not found!'
        ],  404);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $complain = Complain::find($id);
        if ($complain) {
            $complain->delete();
            return response([
                'status'    => true,
                'message'   => 'Complain deleted successfully.',
                'data'      => $complain
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Complain not found!'
        ],  404);
    }

    //update status
    public function status(Request $request, $id)
    {
        $complain = Complain::find($id);
        if ($complain) {
            $complain->status = $request->status;
            $complain->save();

            //notify user 
            $user_notify = new TenantNotification;
            $user_notify->tenant_id = $complain->user_id;
            $user_notify->type = 'Urgent';
            $user_notify->title = 'Complains Status Changed';
            $user_notify->content = 'You complain status has been changed to: ' . $request->status;
            $user_notify->name = 'Rent A Roof';
            $user_notify->redirect = '/tenant/complain_management';
            $user_notify->save();
            event(new NotificationSent($user_notify));

            return response([
                'status'    => true,
                'message'   => 'Status updated successfully.',
                'data'      => $complain
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Complain not found!'
        ],  404);
    }
}
