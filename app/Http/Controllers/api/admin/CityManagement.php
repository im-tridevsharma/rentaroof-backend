<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\State;
use App\Models\City;

class CityManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $cities = City::all();
        return response([
            'status'  => true,
            'message' => 'Cities fetched successfully.',
            'data'    => $cities->load("state")
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
            'state_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response([
                'status'  => false,
                'message' => 'Some errors occured.',
                'error'   => $validator->errors()
            ], 400);
        }

        //check if state exists with provided id
        if (!State::find($request->state_id)) {
            return response([
                'status'  => false,
                'message' => 'Failed to find state you selected.'
            ], 404);
        }

        //save state
        $city = City::create($validator->Validated());
        if ($city) {
            return response([
                'status'  => true,
                'message' => 'New city created successfully.',
                'data'    => $city
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'Failed to add new city.'
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
        $city = City::find($id);
        if ($city) {
            return response([
                'status'  => true,
                'message' => 'City fetched successfully.',
                'data'    => $city
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'City not found.'
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
            'name' => 'required|string|max:50',
            'state_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response([
                'status'  => false,
                'message' => 'Some errors occured.',
                'error'   => $validator->errors()
            ], 400);
        }

        //check if country exists with provided id
        if (!State::find($request->state_id)) {
            return response([
                'status'  => false,
                'message' => 'Failed to find state you selected.'
            ], 404);
        }

        $city = City::find($id);
        if ($city) {
            $city->name = $request->name;
            $city->state_id = $request->state_id;
            $city->save();

            return response([
                'status'  => true,
                'message' => 'City updated successfully.',
                'data'    => $city
            ]);
        }

        return response([
            'status'  => false,
            'message' => 'City not found.'
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
        $city = City::find($id);
        if ($city) {
            $city->delete();

            return response([
                'status'  => true,
                'message' => 'City deleted successfully.',
                'data'    => $city
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'City not found.'
        ], 404);
    }
}
