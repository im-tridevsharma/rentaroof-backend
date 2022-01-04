<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\City;
use App\Models\Location;

class LocationManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $locations = Location::all();
        return response([
            'status'  => true,
            'message' => 'Cities fetched successfully.',
            'data'    => $locations->load("city")
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
            'city_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response([
                'status'  => false,
                'message' => 'Some errors occured.',
                'error'   => $validator->errors()
            ], 400);
        }

        //check if city exists with provided id
        if (!City::find($request->city_id)) {
            return response([
                'status'  => false,
                'message' => 'Failed to find city you selected.'
            ], 404);
        }

        //save city
        $location = Location::create($validator->validated());
        if ($location) {
            return response([
                'status'  => true,
                'message' => 'New location created successfully.',
                'data'    => $location
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'Failed to add new location.'
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
        $location = Location::find($id);
        if ($location) {
            return response([
                'status'  => true,
                'message' => 'Location fetched successfully.',
                'data'    => $location
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'Location not found.'
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
            'city_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response([
                'status'  => false,
                'message' => 'Some errors occured.',
                'error'   => $validator->errors()
            ], 400);
        }

        //check if country exists with provided id
        if (!City::find($request->city_id)) {
            return response([
                'status'  => false,
                'message' => 'Failed to find city you selected.'
            ], 404);
        }

        $location = Location::find($id);
        if ($location) {
            $location->name = $request->name;
            $location->city_id = $request->city_id;
            $location->save();

            return response([
                'status'  => true,
                'message' => 'Location updated successfully.',
                'data'    => $location
            ]);
        }

        return response([
            'status'  => false,
            'message' => 'Location not found.'
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
        $location = Location::find($id);
        if ($location) {
            $location->delete();

            return response([
                'status'  => true,
                'message' => 'Location deleted successfully.',
                'data'    => $location
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'Location not found.'
        ], 404);
    }
}
