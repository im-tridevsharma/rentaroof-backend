<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Sos as SosModel;
use Tymon\JWTAuth\Facades\JWTAuth;

class Sos extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $allsos = SosModel::where("fired_by_id", JWTAuth::user()->id);

        if ($allsos) {
            return response([
                'status'    => true,
                'message'   => 'All Sos fetched successfully.',
                'data'      => $allsos
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
        $validator = Validator::make($request->input(), [
            'sos_content'   => 'required|string|max:250'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some error occured.',
                'error'     => $validator->errors()
            ], 404);
        }

        $sos = new SosModel;
        $sos->fired_by_id       = $user->id;
        $sos->user_type         = $user->role;
        $sos->name              = $user->first . ' ' . $user->last;
        $sos->email             = $user->email;
        $sos->sos_content       = $request->sos_content;
        $sos->resolve_message   = '';
        $sos->status_history    = json_encode([]);

        if ($sos->save()) {
            //code to notify user and admin

            return response([
                'status'    => true,
                'message'   => 'Sos fired successfully.',
                'data'      => $sos->only(['sos_content'])
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
        $sos = SosModel::where("fired_by_id", JWTAuth::user()->id)->find($id);

        if ($sos) {
            return response([
                'status'    =>  true,
                'message'   => 'Sos fetched successfully.',
                'data'      => $sos
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Sos not found.'
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
        $sos = SosModel::where("fired_by_id", JWTAuth::user()->id)->find($id);

        if ($sos) {
            $sos->delete();
            return response([
                'status'    => true,
                'message'   => 'Sos deleted successfully.',
                'data'      => $sos
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Sos not found.'
        ], 404);
    }
}
