<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\IboEarning;
use App\Models\KycVerification;
use App\Models\PropertyDeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{

    public function __construct()
    {
        $this->middleware('jwt.verify');
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response([
            'status'  => false,
            'message' => 'Api endpoint is not supported.'
        ], 404);
    }

    /**
     * fetch user information based on condition
     */

    public function search(Request $request)
    {
        $type   = $request->input('type') ? explode(",", $request->type) : [];
        $select = $request->input('select') ? explode(",", $request->select) : [];

        $users = User::select($select)->whereIn('role', $type)->get();

        if ($users) {
            return response([
                'status'    => true,
                'message'   => 'Users fetched successfully.',
                'data'      => $users
            ]);
        }

        return response([
            'status'    => false,
            'message'   => 'Query is not valid!'
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
        $rules = [
            'first'        => 'required|string|between:2,25',
            'mobile'       => 'required|digits_between:10,12',
            'gender'       => 'required|in:male,female,other',
            'profile_pic'  => 'mimes:jpg,png,jpeg|max:2048'
        ];

        $errorMessages = [
            'doc_type.required' => "Please select a document type to upload.",
            'document.required' => "Please upload a document you have selected.",
        ];

        if (isset($request->mode) && $request->mode == 'kyc') {
            $rules['doc_type']  = 'required|string';
            $rules['document']  = 'required|mimes:jpg,png,jpeg,pdf|max:2048';
        } else {
            $rules['email'] = 'required|email';
        }

        $validator = Validator::make($request->all(), $rules, $errorMessages);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $user = User::find($id);

        $profile_pic_url = '';
        if ($request->hasFile('profile_pic')) {
            $upload_dir = "/uploads/users/profile_pic";
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('profile_pic'), 'public');
            $profile_pic_url = Storage::disk('digitalocean')->url($name);

            //remove old image
            $oldimg = $user->profile_pic;
            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
            }
        }

        $signature_url = '';
        if ($request->hasFile('signature')) {
            $upload_dir = "/uploads/users/signature";
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('signature'), 'public');
            $signature_url = Storage::disk('digitalocean')->url($name);

            //remove old image
            $oldimg = $user->signature;
            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
            }
        }

        if ($user) {
            $user->first = $request->first;
            $user->last  = isset($request->last) ? $request->last : '';
            $user->email = isset($request->email) ? $request->email : (!empty($user->email) ? $user->email : NULL);
            $user->mobile = isset($request->mobile) ? $request->mobile : (!empty($user->mobile) ? $user->mobile : NULL);
            $user->gender = isset($request->gender) ? $request->gender : '';
            $user->experience = isset($request->experience) ? $request->experience : NULL;
            $user->operating_since = isset($request->operating_since) ? $request->operating_since : 0;
            $user->ibo_duty_mode = isset($request->ibo_duty_mode) ? $request->ibo_duty_mode : NULL;
            $user->username = isset($request->username) ? $request->username : NULL;
            $user->dob = !empty($request->dob) ? date("Y-m-d", strtotime($request->dob)) : NULL;
            $user->profile_pic = !empty($profile_pic_url) ? $profile_pic_url : $user->profile_pic;
            $user->signature = !empty($signature_url) ? $signature_url : $user->signature;

            if ($user->save()) {
                //save address
                $address = $user->address_id ? Address::find($user->address_id) : new Address;
                
                if ($address) {
                    $address->user_id = $user->id;
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
                        $user->address_id = $address->id;
                        $user->save();
                    }
                }

                //update kyc details
                if (isset($request->mode) && $request->mode == 'kyc') {
                    $kyc = $user->kyc_id ? KycVerification::find($user->kyc_id) : new KycVerification;
                    $kyc->document_type = $request->doc_type;
                    $kyc->user_id = $user->id;
                    $kyc->document_number = '';

                    //upload kyc document to server
                    if ($request->hasFile('document')) {
                        $role = $user->role == 'ibo' ? 'ibos' : $user->role;
                        $upload_dir = "/uploads/" . $role . "/kyc";

                        if (!empty($kyc->document_upload)) {
                            $olddoc = $kyc->document_upload;
                            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($olddoc))) {
                                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($olddoc));
                            }
                        }

                        $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('document'), 'public');
                        $kyc->document_upload = Storage::disk('digitalocean')->url($name);
                    } else {
                        $kyc->document_upload = !empty($kyc->document_upload) ? $kyc->document_upload : '';
                    }

                    if ($kyc->save()) {
                        $user->kyc_id = $kyc->id;
                        $user->save();
                    }
                }

                return response([
                    'status'    => true,
                    'message'   => 'Profile updated successfully.',
                    'data'      => $user
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Requested profile not found!'
        ], 404);
    }

    /**
     * Update the password
     */
    public function password(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'current_password'  => 'required|string|min:8|max:20',
            'new_password'      => 'required|string|min:8|max:20',
            'confirm_password'  => 'required|string|same:new_password'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $user = User::find($id);
        if ($user) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response([
                    'status'    => false,
                    'message'   => 'Some errors occured.',
                    'error'     => ['current_password' => ['Current password is not valid.']]
                ], 400);
            }

            $user->password = bcrypt($request->new_password);
            if ($user->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Password has been changed successfully.'
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found!'
        ], 404);
    }

    public function show($id)
    {
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        return response([
            'status'   => false,
            'message'  => "Api endpoint is not supported."
        ]);
    }

    //getDeals
    public function getDeals()
    {
        $user = JWTAuth::user();
        if ($user) {
            $deals = PropertyDeal::where("created_by", $user->id)->get()->map(function ($d) {
                $for = User::select(["first", "last"])->find($d->offer_for);
                $d->user = $for;
                return $d;
            });

            return response([
                'status'    => true,
                'message'   => 'Deals fetched successfully.',
                'data'      => $deals
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found!'
        ], 404);
    }

    //get_earnings
    public function get_ibo_earnings()
    {
        $user = JWTAuth::user();
        if ($user) {
            $earnings = IboEarning::where("ibo_id", $user->id)->get();

            return response([
                'status'    => true,
                'message'   => 'Earnings fetched successfully.',
                'data'      => $earnings
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found.'
        ], 404);
    }

    //ibo income cards
    public function income_cards()
    {
        $ibo = JWTAuth::user();

        $this_month_total = 0.0;
        $last_month_total = 0.0;
        $income_breakdown = 0.0;
        $this_year_total  = 0.0;
        $last_year_total  = 0.0;
        
        //this month
        $this_month_earning = IboEarning::whereMonth("created_at", date("m"))->whereYear("created_at", date("Y"))->where("ibo_id", $ibo->id)->get();
        foreach($this_month_earning as $e){
            $this_month_total += floatval($e->amount);
        }

        //last month
        $last_month_earning = IboEarning::whereMonth("created_at", date("m", strtotime('last month')))->whereYear("created_at", date("Y", strtotime('last month')))->where("ibo_id", $ibo->id)->get();
        foreach($last_month_earning as $e){
            $last_month_total += floatval($e->amount);
        }

        $income_breakdown = $last_month_total - $this_month_total;

        //this year
        $this_yaer_earning = IboEarning::whereYear("created_at", date("Y"))->where("ibo_id", $ibo->id)->get();
        foreach($this_yaer_earning as $e){
            $this_year_total += floatval($e->amount);
        }

        //last year
        $last_yaer_earning = IboEarning::whereYear("created_at", date("Y", strtotime('last year')))->where("ibo_id", $ibo->id)->get();
        foreach($last_yaer_earning as $e){
            $last_year_total += floatval($e->amount);
        }

        return response([
            'status'    => true,
            'message'   => 'Ibo Earning Cards fetched successfully.',
            'data'      => [
                "this_month"    => $this_month_total,
                "per_month"     => $last_month_total,
                "breakdown"     => $income_breakdown,
                "breakdown_sign" => $income_breakdown > 0 ? '-' : '+',
                "this_year"     => $this_year_total,
                "last_year"     => $last_year_total
            ]
        ], 200);
    }

    //deals earning
    public function ibo_deals_earning()
    {
        $ibo = JWTAuth::user();
        $earnings = IboEarning::where("ibo_id", $ibo->id)->get()->map(function($d){
            $deal = PropertyDeal::find($d->deal_id);
            $d->is_closed = $deal ? $deal->is_closed : 0;
            $d->user = User::find($deal->offer_for ?? 0)->first ?? '';

            return $d;
        });

        return response([
            'status'    => true,
            'message'   => 'Deals Earning fetched successfully.',
            'data'      => $earnings
        ], 200);
    }

    //earning for year month wise
    public function earning_for_year()
    {
        $ibo = JWTAuth::user();
        $years = [];
        $months = ['Jan','Feb','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        foreach($months as $m){
            $total = 0;
            $earnings = IboEarning::whereMonth("created_at", date('m', strtotime($m.date('Y'))))->whereYear("created_at", date('Y'))->where("ibo_id", $ibo->id)->get();
            foreach($earnings as $e){
                $total += floatval($e->amount);
            }

            $years[$m] = $total;
        }

        return response([
            'status'    => true,
            'message'   => 'Year\'s income month wise fetched successfully.',
            'data'      => $years
        ], 200);
    }
}
