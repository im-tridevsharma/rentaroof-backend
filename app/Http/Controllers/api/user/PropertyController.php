<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Property;
use App\Models\PropertyEssential;
use App\Models\PropertyGallery;
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
    public function show($id)
    {
        $property = Property::find($id);
        if ($property) {
            return response([
                'status'    => true,
                'message'   => 'Property fetched successfully.',
                'data'      => $property
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Property not found.'
        ], 404);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Property  $property
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
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

        $property = Property::find($id);
        if ($property) {

            $property->name = $request->name;
            $property->short_description = $request->short_description;
            $property->for = $request->for;
            $property->type = $request->type;
            $property->posting_as = $request->posting_as;
            $property->ownership_type = $request->ownership_type;
            $property->furnished_status = $request->furnished_status;
            $property->bedrooms = $request->bedrooms;
            $property->balconies = $request->balconies;
            $property->floors = $request->floors;
            $property->bathrooms = $request->bathrooms;
            $property->super_area = $request->super_area;
            $property->super_area_unit = $request->super_area_unit;
            $property->available_from = $request->available_from;
            $property->monthly_rent = $request->monthly_rent;
            $property->security_amount = $request->security_amount;
            $property->age_of_construction = $request->age_of_construction;

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

            try {
                if ($property->save()) {
                    return response([
                        'status'    => true,
                        'message'   => 'Property updated successfully.',
                        'data'      => $property
                    ], 200);
                }

                return response([
                    'status'    => false,
                    'message'   => 'Unable to update data.'
                ]);
            } catch (Exception $e) {
                return response([
                    'status'    => false,
                    'message'   => $e,
                ], 500);
            }
        }

        return response([
            'status'    => false,
            'message'   => 'Property not found'
        ], 404);
    }

    //save property amenity
    public function amenity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'propertyId' => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errros occured.',
                'error'     => $validator->errors()
            ]);
        }

        $amenities = json_encode($request->amenities);
        $p = Property::find($request->propertyId);
        $p->amenities = $amenities;

        if ($p->save()) {
            return response([
                'status'    => true,
                'message'   => 'Amenities added successfully.'
            ], 200);
        }

        return response([
            'staus'     => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }

    //save property essential
    public function essential(Request $request)
    {
        $essential = new PropertyEssential;
        $essential->property_id = $request->propertyId;

        $essential->school = isset($request->school) ? $request->school : '';
        $essential->hospital = isset($request->hospital) ? $request->hospital : '';
        $essential->airport = isset($request->airport) ? $request->airport : '';
        $essential->train = isset($request->train) ? $request->train : '';
        $essential->market = isset($request->market) ? $request->market : '';
        $essential->restaurent = isset($request->restaurent) ? $request->restaurent : '';

        if ($essential->save()) {
            $p = Property::find($request->propertyId);
            $p->property_essential_id = $essential->id;
            $p->save();
            return response([
                'status'    => true,
                'message'   => 'Essential added successfully.'
            ], 200);
        }

        return response([
            'staus'     => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Property  $property
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $property = Property::find($id);
        if ($property) {
            if ($property->delete()) {
                //delete address if exist
                $address = Address::find($property->address_id);
                if ($address) {
                    $address->delete();
                }

                //delete gallery if exist
                $gallery = PropertyGallery::find($property->gallery_id);
                if ($gallery) {
                    $gallery->delete();
                    //delete all the files from storage server
                }

                //delete property_essential if exist
                $essential = PropertyEssential::find($property->property_essential_id);
                if ($essential) {
                    $essential->delete();
                }

                return response([
                    'status'    => true,
                    'message'   => 'Property deleted successfully',
                    'data'      => $property
                ]);
            }

            return response([
                'status'    => false,
                'message'   => 'Unable to delete property.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Property not found.'
        ], 404);
    }
}
