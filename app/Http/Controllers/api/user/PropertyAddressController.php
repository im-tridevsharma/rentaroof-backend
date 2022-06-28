<?php

namespace App\Http\Controllers\api\user;

use App\Events\NotificationSent;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\IboNotification;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

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
            'propertyId' => 'required',
            'lattitude' => 'required',
            'longitude' => 'required',
            'pincode'   => 'required|max:6|min:6',
            'country'   => 'required',
            'state'     => 'required',
            'city'      => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        //check for duplicate
        $is_duplicate = Address::where("area", $request->area)
            ->orWhere("house_number", $request->house_number)->count();

        if ($is_duplicate) {
            return response([
                'status'    => false,
                'message'   => 'Duplicate details found! Please check your property details.'
            ], 401);
        }


        $address = new Address;

        $address->landmark = isset($request->landmark) ? $request->landmark : '';
        $address->house_number = isset($request->house_number) ? $request->house_number :  '';
        $address->lat = isset($request->lattitude) ? $request->lattitude : 0.0;
        $address->long = isset($request->longitude) ? $request->longitude : 0.0;
        $address->pincode = isset($request->pincode) ? $request->pincode : '';
        $address->country = isset($request->country) ? $request->country : '';
        $address->state = isset($request->state) ? $request->state : '';
        $address->city = isset($request->city) ? $request->city : '';
        $address->zone = isset($request->zone) ? $request->zone : '';
        $address->area = isset($request->area) ? $request->area : '';
        $address->sub_area = isset($request->sub_area) ? $request->sub_area : '';
        $address->route = isset($request->route) ? $request->route : '';
        $address->neighborhood = isset($request->neighborhood) ? $request->neighborhood : '';
        $address->place_id = isset($request->place_id) ? $request->place_id : '';
        $address->full_address = isset($request->full_address) ? $request->full_address : '';

        $address->property_id = $request->propertyId;
        $address->address_type = 'property';

        if ($address->save()) {
            $p = Property::find($request->propertyId);
            $p->address_id = $address->id;
            $p->country_name = $request->country ?? '';
            $p->state_name = $request->state ?? '';
            $p->city_name = $request->city ?? '';
            $p->pincode = $request->pincode ?? '';
            $p->save();

            //assign property verification to nearest ibo
            if ($p->ibo !== JWTAuth::user()->id) {
                $latitude = $request->lattitude ?? 0;
                $longitude = $request->longitude ?? 0;
                $ibos = DB::table("addresses")
                    ->select("user_id", DB::raw("6371 * acos(cos(radians(" . $latitude . "))
                     * cos(radians(lat)) * cos(radians(`long`) - radians(" . $longitude . "))
                     + sin(radians(" . $latitude . ")) * sin(radians(lat))) AS distance"))
                    ->having('distance', '<', 50)
                    ->orderBy('distance', 'asc')->distinct()
                    ->where("user_id", "!=", NULL)->get();

                if (count($ibos) > 0) {
                    foreach ($ibos as $ibo) {
                        $user = User::where("role", "ibo")->where("id", $ibo->user_id)->first();
                        if ($user) {

                            $data = [
                                'property_id'   => $p->id,
                                'ibo_id'        => $user->id,
                                'message'       => 'Auto assigned by Rent A Roof.',
                                'created_at'    => date("Y-m-d H:i:s"),
                                'updated_at'    => date("Y-m-d H:i:s")
                            ];

                            $is = DB::table('property_verifications')->where("property_id", $p->id)->first();

                            if ($is) {
                                $data['status'] = 'pending';
                                $data['reason_for_rejection'] = '';

                                DB::table('property_verifications')->where("id", $is->id)->update($data);
                                return response([
                                    'status'    => true,
                                    'message'   => 'Assigned for verification successfully.',
                                    'data'      => $data
                                ], 200);
                            }
                            DB::table('property_verifications')->insert($data);

                            //notify ibo
                            $ibo_notify = new IboNotification;
                            $ibo_notify->ibo_id = $user->id;
                            $ibo_notify->title = 'Property Verification Request';
                            $ibo_notify->content = 'You have been assigned property verification by Rent A Roof.';
                            $ibo_notify->type = 'Urgent';
                            $ibo_notify->name = 'Rent A Roof';
                            $ibo_notify->redirect = '/ibo/property-verification';

                            $ibo_notify->save();
                            event(new NotificationSent($ibo_notify));
                        }
                    }
                }
            }

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
            'propertyId' => 'required',
            'lattitude' => 'required',
            'longitude' => 'required',
            'pincode'   => 'required|max:6|min:6',
            'country'   => 'required',
            'state'     => 'required',
            'city'      => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $address = Address::find($id);
        $address = $address ? $address : new Address;

        if ($address) {
            $address->landmark = isset($request->landmark) ? $request->landmark : '';
            $address->house_number = isset($request->house_number) ? $request->house_number :  '';
            $address->lat = isset($request->lattitude) ? $request->lattitude : 0.0;
            $address->long = isset($request->longitude) ? $request->longitude : 0.0;
            $address->pincode = isset($request->pincode) ? $request->pincode : '';
            $address->country = isset($request->country) ? $request->country : '';
            $address->state = isset($request->state) ? $request->state : '';
            $address->city = isset($request->city) ? $request->city : '';
            $address->zone = isset($request->zone) ? $request->zone : '';
            $address->area = isset($request->area) ? $request->area : '';
            $address->sub_area = isset($request->sub_area) ? $request->sub_area : '';
            $address->route = isset($request->route) ? $request->route : '';
            $address->neighborhood = isset($request->neighborhood) ? $request->neighborhood : '';
            $address->place_id = isset($request->place_id) ? $request->place_id : '';
            $address->full_address = isset($request->full_address) ? $request->full_address : '';

            if ($address->save()) {
                $p = Property::find($request->propertyId);
                $p->address_id = $address->id;
                $p->country_name = $request->country ?? '';
                $p->state_name = $request->state ?? '';
                $p->city_name = $request->city ?? '';
                $p->pincode = $request->pincode ?? '';
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
