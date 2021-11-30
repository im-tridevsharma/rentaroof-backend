<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Complain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ComplainController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = JWTAuth::user();
        if ($user) {
            $complains = Complain::where("user_id", $user->id)->orderBy("id", "desc")->get();
            return response([
                'status'    => true,
                'message'   => 'Complains fetched successfully.',
                'data'      => $complains
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found!'
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'customer_id'   => 'required',
            'fullname'      => 'required',
            'email_or_phone' => 'required',
            'subject'       => 'required|max:200',
            'details'       => 'required|max:500'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        //save
        $user = JWTAuth::user();
        if ($user) {
            $complain = new Complain;
            $complain->user_id = $user->id;
            $complain->customer_id = $request->customer_id;
            $complain->fullname = $request->fullname;
            $complain->email_or_phone = $request->email_or_phone;
            $complain->subject = $request->subject;
            $complain->details = $request->details;
            $complain->status = 'waiting';

            if ($complain->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Complain created successfully.',
                    'data'      => $complain
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong! Please try again.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found.'
        ], 404);
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
        if ($user) {
            $complain = Complain::where("id", $id)->where("user_id", $user->id)->first();

            if ($complain) {
                return response([
                    'status'    => true,
                    'message'   => 'Complain fetched successfully.',
                    'data'      => $complain
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Complain not found.'
            ], 404);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found.'
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
        $validator = Validator::make($request->input(), [
            'customer_id'   => 'required',
            'fullname'      => 'required',
            'email_or_phone' => 'required',
            'subject'       => 'required|max:200',
            'details'       => 'required|max:500'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        //save
        $user = JWTAuth::user();
        if ($user) {
            $complain = Complain::find($id);
            if ($complain) {
                $complain->user_id = $user->id;
                $complain->customer_id = $request->customer_id;
                $complain->fullname = $request->fullname;
                $complain->email_or_phone = $request->email_or_phone;
                $complain->subject = $request->subject;
                $complain->details = $request->details;

                if ($complain->save()) {
                    return response([
                        'status'    => true,
                        'message'   => 'Complain updated successfully.',
                        'data'      => $complain
                    ], 200);
                }

                return response([
                    'status'    => false,
                    'message'   => 'Something went wrong! Please try again.'
                ], 500);
            }

            return response([
                'status'    => false,
                'message'   => 'Complain not found.'
            ], 404);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found.'
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
        if ($user) {
            $complain = Complain::where("id", $id)->where("user_id", $user->id)->first();

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
                'message'   => 'Complain not found.'
            ], 404);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found.'
        ], 404);
    }
}
