<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\Property;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PropertyAddressController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response([
            'status'    => false,
            'message'   => 'Action not allowed'
        ], 401);
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
            'lattitude' => 'required',
            'longitude' => 'required',
            'pincode'   => 'required|max:6|min:6'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $address = new Address;

        $address->landmark = isset($request->landmark) ? $request->landmark : '';
        $address->house_number = isset($request->house_number) ? $request->house_number : '';
        $address->lat = isset($request->lattitude) ? $request->lattitude : '';
        $address->long = isset($request->longitude) ? $request->longitude : '';
        $address->pincode = isset($request->pincode) ? $request->pincode : '';
        $address->country = isset($request->country) ? $request->country : NULL;
        $address->state = isset($request->state) ? $request->state : NULL;
        $address->city = isset($request->city) ? $request->city : NULL;
        $address->full_address = isset($request->full_address) ? $request->full_address : '';

        $address->property_id = $request->propertyId;
        $address->address_type = 'property';

        if ($address->save()) {
            $p = Property::find($request->propertyId);
            $p->address_id = $address->id;
            $p->country_name = !empty($request->country) ? Country::find($request->country)->name : '';
            $p->state_name = !empty($request->state) ? State::find($request->state)->name : '';
            $p->city_name = !empty($request->city) ? City::find($request->city)->name : '';
            $p->pincode = !empty($request->pincode) ? $request->pincode : '';
            $p->save();

            return response([
                'status'    => true,
                'message'   => 'Address added successfully.',
                'data'      => $address
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
        $address = Address::find($id);
        if ($address) {
            return response([
                'status'    => true,
                'message'   => 'Address fetched successfully.',
                'data'      => $address
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Address not found.'
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
            'lattitude' => 'required',
            'longitude' => 'required',
            'pincode'   => 'required|max:6|min:6'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $address = Address::find($id);

        if ($address) {
            $address->landmark = isset($request->landmark) ? $request->landmark : $address->landmark;
            $address->house_number = isset($request->house_number) ? $request->house_number :  $address->house_number;
            $address->lat = isset($request->lattitude) ? $request->lattitude : $address->lat;
            $address->long = isset($request->longitude) ? $request->longitude : $address->long;
            $address->pincode = isset($request->pincode) ? $request->pincode : $address->pincode;
            $address->country = isset($request->country) ? $request->country : $address->country;
            $address->state = isset($request->state) ? $request->state : $address->state;
            $address->city = isset($request->city) ? $request->city : $address->city;
            $address->full_address = isset($request->full_address) ? $request->full_address : $address->full_address;

            if ($address->save()) {
                $p = Property::find($request->propertyId);
                $p->address_id = $address->id;
                $p->country_name = !empty($request->country) ? Country::find($request->country)->name : '';
                $p->state_name = !empty($request->state) ? State::find($request->state)->name : '';
                $p->city_name = !empty($request->city) ? City::find($request->city)->name : '';
                $p->pincode = !empty($request->pincode) ? $request->pincode : '';
                $p->save();

                return response([
                    'status'    => true,
                    'message'   => 'Address updated successfully.',
                    'data'      => $address
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Address not found.'
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
        $address = Address::find($id);

        if ($address) {
            $address->delete();
            return response([
                'status'    => true,
                'message'   => 'Address deleted successfully.',
                'data'      => $address
            ]);
        }

        return response([
            'status'    =>  false,
            'message'   => 'Address not found.'
        ], 404);
    }
}
