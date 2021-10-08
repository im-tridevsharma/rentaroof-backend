<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class PropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $properties = Property::where("posted_by", JWTAuth::user()->id)->get();
        if ($properties) {
            return response([
                'status'    => true,
                'message'   => 'Properties fetched successfully.',
                'data'      => $properties
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
            'name'  => 'required|string|between:2,100',
            'short_description' => 'required|string|max:255',
            'for'   => 'required|in:rent,sale',
            'type'  => 'required',
            'posting_as'    => 'required|in:full_house,sharing_basis',
            'ownership_type'    => 'required|in:sole,joint,ownership',
            'furnished_status'  => 'required|in:furnished,unfurnished,semi-furnished,ongoing',
            'bedrooms'  => 'required',
            'balconies' => 'required',
            'floors'    => 'required',
            'bathrooms' => 'required',
            'super_area'    => 'required',
            'super_area_unit'   => 'required|in:sqft,cm,m',
            'available_from'    => 'required',
            'monthly_rent'      => 'required',
            'security_amount'   => 'required',
            'age_of_construction'   => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured',
                'error'     => $validator->errors()
            ], 400);
        }

        $property = new Property($request->input());

        $property->property_code = 'RARP-0' . rand(11111, 99999) . '0';

        if (isset($request->custom_bedrooms) && !empty($request->custom_bedrooms)) {
            $property->bedrooms = $request->custom_bedrooms;
        }

        if (isset($request->custom_bathrooms) && !empty($request->custom_bathrooms)) {
            $property->bathrooms = $request->custom_bathrooms;
        }

        if (isset($request->custom_balconies) && !empty($request->custom_balconies)) {
            $property->balconies = $request->custom_balconies;
        }

        if (isset($request->custom_floors) && !empty($request->custom_floors)) {
            $property->floors = $request->custom_floors;
        }

        if (isset($request->available_immediately) && $request->available_immediately == 'on') {
            $property->available_immediately = 1;
        }

        $property->description = $request->description ? $request->description : '';

        $property->address_id = NULL;
        $property->front_image = '';
        $property->posted_by = JWTAuth::user()->id;

        try {
            if ($property->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Property added successfully.',
                    'data'      => $property
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Unable to save data.'
            ]);
        } catch (Exception $e) {
            return response([
                'status'    => false,
                'message'   => $e,
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Property  $property
     * @return \Illuminate\Http\Response
     */
    public function show(Property $property)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Property  $property
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Property $property)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Property  $property
     * @return \Illuminate\Http\Response
     */
    public function destroy(Property $property)
    {
        //
    }
}
