<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Country;

class CountryManagement extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response([
            'status'  => true,
            'message' => 'Countries fetched successfully.',
            'data'    => Country::all()
        ], 200);
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
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:3'
        ]);

        if( $validator->fails() )
        {
            return response([
                'status'  => false,
                'message' => 'Some errors occured.',
                'error'   => $validator->errors()
            ], 400);
        }

        $country = Country::create($validator->Validated());
        if($country){
            return response([
                'status'  => true,
                'message' => 'New country created successfully.',
                'data'    => $country
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'Failed to add new country.'
        ],404);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $country = Country::find($id);
        if($country)
        {
            return response([
                'status'  => true,
                'message' => 'Country fetched successfully.',
                'data'    => $country
            ],200);
        }

        return response([
            'status'  => false,
            'message' => 'Country not found.'
        ],404);
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
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:3'
        ]);

        if( $validator->fails() )
        {
            return response([
                'status'  => false,
                'message' => 'Some errors occured.',
                'error'   => $validator->errors()
            ], 400);
        }

        $country = Country::find($id);
        if($country)
        {
            $country->name = $request->name;
            $country->code = $request->code;
            $country->save();

            return response([
                'status'  => true,
                'message' => 'Country updated successfully.',
                'data'    => $country
            ]);
        }

        return response([
            'status'  => false,
            'message' => 'Country not found.'
        ],404);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $country = Country::find($id);
        if($country)
        {
            $country->delete();

            return response([
                'status'  => true,
                'message' => 'Country deleted successfully.',
                'data'    => $country
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'Country not found.'
        ],404);
    }
}
