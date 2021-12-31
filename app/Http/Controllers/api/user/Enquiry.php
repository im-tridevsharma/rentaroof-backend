<?php

namespace App\Http\Controllers\api\user;

use App\Events\AdminNotificationSent;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\AdminNotification;
use App\Models\City;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Enquiry as ModelsEnquiry;
use App\Models\State;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class Enquiry extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.verify', ['except' => ['store','findAnAgent']]);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $enquiries = ModelsEnquiry::where("user_id", JWTAuth::user()->id)->get();

        if ($enquiries) {
            return response([
                'status'    => true,
                'message'   => 'Enquiries fetched successfully.',
                'data'      => $enquiries
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
            'name'          => 'required|string|between:2, 50',
            'description'   => 'required|string|max:500',
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

        $enquiry = new ModelsEnquiry;
        $enquiry->title = $request->title;
        $enquiry->description = $request->description;
        $enquiry->email = $request->email;
        $enquiry->mobile = $request->mobile;
        $enquiry->name = isset($request->name) ? $request->name : '';
        $enquiry->user_id = JWTAuth::user() ? JWTAuth::user()->id : NULL;
        $enquiry->type = isset($request->type) ? $request->type : '';
        $enquiry->property_id = isset($request->property_id) ? $request->property_id : '';
        $enquiry->system_ip = $request->ip();

        if ($enquiry->save()) {
            //notify admin
            $an = new AdminNotification;
            $an->content = 'You have new enquiry by user: ' . $request->name;
            $an->type  = 'Urgent';
            $an->title = 'New Enquiry Arrived';
            $an->redirect = '/admin/enquiries';
            $an->save();
            event(new AdminNotificationSent($an));

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
        $enquiry = ModelsEnquiry::where("user_id", JWTAuth::user()->id)->find($id);

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
        $validator = Validator::make($request->all(), [
            'title'         => 'required|string|between:2,100',
            'name'          => 'required|string|between:2, 50',
            'description'   => 'required|string|max:500',
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

        $enquiry = ModelsEnquiry::where("user_id",  JWTAuth::user()->id)->find($id);

        if ($enquiry) {

            $enquiry->title = $request->title;
            $enquiry->description = $request->description;
            $enquiry->email = $request->email;
            $enquiry->mobile = $request->mobile;
            $enquiry->name = isset($request->name) ? $request->name : (!empty($enquiry->name) ? $enquiry->name : '');
            $enquiry->user_id = isset($request->user_id) ? $request->user_id : (!empty($enquiry->user_id) ? $enquiry->user_id : '');
            $enquiry->property_id = isset($request->property_id) ? $request->property_id : (!empty($enquiry->property_id) ? $enquiry->property_id : '');
            $enquiry->type = isset($request->type) ? $request->type : (!empty($enquiry->type) ? $enquiry->type : '');
            $enquiry->system_ip = $request->ip();

            if ($enquiry->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Enquiry updated successfully.',
                    'data'      => $enquiry
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Enquiry not found.'
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
        $enquiry = ModelsEnquiry::where("user_id", JWTAuth::user()->id)->find($id);

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

    //fina an agent
    public function findAnAgent(Request $request)
    {
        if($request->has('search')){

            $country_ids = Country::where(function($q) use($request) {
                $q->orWhere("name", "like", "%".$request->search."%");
            })->pluck('id')->toArray();

            $state_ids = State::where(function($q) use($request) {
                $q->orWhere("name", "like", "%".$request->search."%");
            })->pluck('id')->toArray();

            $city_ids = City::where(function($q) use($request) {
                $q->orWhere("name", "like", "%".$request->search."%");
            })->pluck('id')->toArray();

            $address_ids = Address::where(function($q) use($request, $country_ids, $state_ids, $city_ids) {
                $q->orWhere("landmark", "like", "%".$request->search."%");
                $q->orWhere("full_address", "like", "%".$request->search."%");
                $q->orWhereIn("country", $country_ids);
                $q->orWhereIn("state", $state_ids);
                $q->orWhereIn("city", $city_ids);
            })->pluck('id')->toArray();
            
            $ibos = User::select(['id','first','last','email','mobile','kyc_id','profile_pic','address_id','is_logged_in','system_userid'])->where("role", "ibo")->where(function($q) use($request, $address_ids) {
                $q->orWhere("first", "like", "%".$request->search."%");
                $q->orWhere("last", "like", "%".$request->search."%");
                $q->orWhere("email", "like", "%".$request->search."%");
                $q->orWhere("mobile", "like", "%".$request->search."%");
                $q->orWhereIn("address_id", $address_ids);
            })->get()->map(function($m){
                $address = Address::find($m->address_id);
                if($address){
                    $iboaddress = [
                        "country" => Country::find($address->country)->name??'',
                        "state" => State::find($address->state)->name??'',
                        "city" => City::find($address->city)->name??'',
                        "full_address"  => $address->full_address,
                        "landmark"      => $address->landmark
                    ];
                    $m->address = $iboaddress;
                }else{
                    $m->address_id = 0;
                }
                
                $m->kyc_id = $m->kyc_id ?? 0;

                return $m;
            });

            return response([
                'status'    => true,
                'message'   => 'Data fetched successfully.',
                'data'      => $ibos
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Search keyword not found!'
        ], 422);
    }
}
