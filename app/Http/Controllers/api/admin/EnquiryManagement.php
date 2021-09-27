<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Enquiry;

class EnquiryManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $enquires = Enquiry::all();

        if ($enquires) {
            return response([
                'status'    => true,
                'message'   => 'Enquiries fetched successfully.',
                'data'      => $enquires
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!'
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
        $validator = Validator::make($request->all(), [
            'title'         => 'required|string|between:2,100',
            'description'   => 'required|string|max:250',
            'email'         => 'required|email',
            'mobile'        => 'required|digits_between:10,12'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some error occured.',
                'error'     => $validator->errors()
            ], 404);
        }

        $enquiry = new Enquiry;
        $enquiry->title = $request->title;
        $enquiry->description = $request->description;
        $enquiry->email = $request->email;
        $enquiry->mobile = $request->mobile;
        $enquiry->name = isset($request->name) ? $request->name : '';
        $enquiry->user_id = isset($request->user_id) ? $request->user_id : '';
        $enquiry->subject = isset($request->subject) ? $request->subject : '';
        $enquiry->type = isset($request->type) ? $request->type : '';
        $enquiry->system_ip = $request->ip();

        if ($enquiry->save()) {
            return response([
                'status'    => true,
                'message'   => 'Enquiry saved successfully.',
                'data'      => $enquiry
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
        $enquiry = Enquiry::find($id);

        if ($enquiry) {
            return response([
                'status'    => true,
                'message'   => 'Enquiry fetched successfully.',
                'data'      => $enquiry
            ], 200);
        }

        return response([
            'status'    => true,
            'message'   => 'Enquiry not found.'
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $enquiry = Enquiry::find($id);

        if ($enquiry) {
            $enquiry->delete();
            return response([
                'status'    => true,
                'message'   => 'Enquiry deleted successfully.',
                'data'      => $enquiry
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Enquiry not found.'
        ], 404);
    }
}
