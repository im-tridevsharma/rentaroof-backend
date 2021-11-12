<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\KycVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class LandlordManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //fetch landlords
        $landlords = User::where("role", "landlord")->get();

        return response([
            'status'  => true,
            'message' => 'Landlords fetched successfully.',
            'data'    => $landlords->load('kyc')
        ], 200);
    }

    /**
     * Return no. of landlords
     */
    public function total()
    {
        $total = User::where("role", "landlord")->count();
        if ($total >= 0) {
            return response([
                "status"    => true,
                "message"   => "Feteched successfully.",
                "data"      => $total
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => "Something went wrong!"
        ], 500);
    }


    //ban landlord
    public function ban($id)
    {
        $landlord = User::where("role", "landlord")->where("id", $id)->first();
        if ($landlord) {
            $landlord->account_status = "banned";
            $landlord->save();

            return response([
                'status'    => true,
                'message'   => "Landlord banned successfully.",
                'data'      => $landlord->load('kyc')
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => "Landlord not found!"
        ], 404);
    }

    //activate landlord
    public function activate($id)
    {
        $landlord = User::where("role", "landlord")->where("id", $id)->first();
        if ($landlord) {
            $landlord->account_status = "activated";
            $landlord->save();

            return response([
                'status'    => true,
                'message'   => "Landlord activated successfully.",
                'data'      => $landlord->load('kyc')
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => "Landlord not found!"
        ], 404);
    }


    /**
     * update kyc status
     */
    public function verify_kyc(Request $request, $id)
    {
        $validator = Validator::make($request->input(), [
            'status'    => 'required|boolean',
            'user_id'   => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured',
                'error'     => $validator->errors()
            ], 400);
        }

        $kyc = KycVerification::where("user_id", $request->user_id)->where("id", $id)->count();
        if ($kyc) {
            $kyc = KycVerification::find($id);
            $kyc->is_verified = $request->status;
            if ($request->status == 0) {
                $kyc->verification_issues = isset($request->reason) ? $request->reason : '';
            } else {
                $kyc->verified_at = date("Y-m-d H:i:s");
                $kyc->verification_issues = '';
            }

            if ($kyc->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Kyc status updated successfully.'
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Details not found.'
        ], 404);
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
            'firstname' => 'required|string|between:2,25',
            'lastname'  => 'required|string|between:2,25',
            'email'     => 'required|email|unique:users',
            'mobile'    => 'required|digits_between:10,12|unique:users',
            'username'  => 'unique:users',
            'gender'    => 'required|in:male,female,other',
            'password'  => 'required|min:6',
            'profile_pic' => 'mimes:png,jpg,jpeg'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $profile_pic_url = '';
        if ($request->hasFile('profile_pic')) {
            $upload_dir = "/uploads/landlord/profile_pic";
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('profile_pic'), 'public');
            $profile_pic_url = Storage::disk('digitalocean')->url($name);
        }

        $user = new User;
        $user->role = "landlord";
        $user->first = $request->firstname;
        $user->last = $request->lastname;
        $user->username = $request->username;
        $user->email = $request->email;
        $user->mobile = $request->mobile;
        $user->gender = $request->gender;
        $user->dob = !empty($request->dob) ? date("Y-m-d", strtotime($request->dob)) : null;
        $user->password = bcrypt($request->password);
        $user->profile_pic = $profile_pic_url;
        $user->system_ip = $request->ip();

        if ($user->save()) {
            $address = new Address;
            $address->user_id = $user->id;
            $address->landmark = isset($request->landmark) ? $request->landmark : '';
            $address->house_number = isset($request->houseno) ? $request->houseno : '';
            $address->country = $request->country;
            $address->state = $request->state;
            $address->city = $request->city;
            $address->pincode = isset($request->pincode) ? $request->pincode : '';
            $address->full_address = isset($request->fulladdress) ? $request->fulladdress : '';

            $kyc = new KycVerification;
            $kyc->user_id = $user->id;
            $kyc->document_type = isset($request->document_type) ? $request->document_type : '';
            $kyc->document_number = isset($request->document_number) ? $request->document_number : '';

            if ($request->hasFile('document_file')) {
                $upload_dir = "/uploads/landlord/kyc";
                $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('document_file'), 'public');
                $kyc->document_upload = Storage::disk('digitalocean')->url($name);
            } else {
                $kyc->document_upload = '';
            }

            if ($address->save() && $kyc->save()) {
                $user->address_id = $address->id;
                $user->kyc_id = $kyc->id;
                $user->save();
            }

            return response([
                'status'    => true,
                'message'   => 'New Landlord added successfully.',
                'user'      => $user
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
        $landlord = User::where('role', 'landlord')->find($id);

        if ($landlord) {
            return response([
                'status'  => true,
                'message' => 'Landlord fetched successfully.',
                'data'    => $landlord->load(['address', 'kyc'])
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'Landlord not found.'
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
            'firstname' => 'required|string|between:2,25',
            'lastname'  => 'required|string|between:2,25',
            'email'     => 'required|email',
            'mobile'    => 'required|digits_between:10,12',
            'gender'    => 'required|in:male,female,other',
            'password'  => 'required|min:6',
            'profile_pic' => 'mimes:png,jpg,jpeg'
        ]);

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
            $upload_dir = "/uploads/landlord/profile_pic";
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('profile_pic'), 'public');
            $profile_pic_url = Storage::disk('digitalocean')->url($name);

            //remove old image
            $oldimg = $user->profile_pic;
            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
            }
        }

        $user->role = "landlord";
        $user->first = $request->firstname;
        $user->last = $request->lastname;
        $user->username = $request->username;
        $user->email = $request->email;
        $user->mobile = $request->mobile;
        $user->gender = $request->gender;
        $user->dob = !empty($request->dob) ? date("Y-m-d", strtotime($request->dob)) : null;
        $user->password = bcrypt($request->password);
        $user->profile_pic = !empty($profile_pic_url) ? $profile_pic_url : $user->profile_pic;
        $user->system_ip = $request->ip();

        if ($user->save()) {
            $address = Address::find($user->address_id);
            $address = $address ? $address : new Address;
            if ($address) {
                $address->user_id = $user->id;
                $address->landmark = isset($request->landmark) ? $request->landmark : (isset($address->landmark) ? $address->landmark : '');
                $address->house_number = isset($request->houseno) ? $request->houseno : (isset($address->house_number) ? $address->house_number : '');
                $address->country = $request->country;
                $address->state = $request->state;
                $address->city = $request->city;
                $address->pincode = isset($request->pincode) ? $request->pincode : (isset($address->pincode) ? $address->pincode : '');
                $address->full_address = isset($request->fulladdress) ? $request->fulladdress : (isset($address->full_address) ? $address->full_address : '');

                $kyc = KycVerification::find($user->kyc_id);
                $kyc = $kyc ? $kyc : new KycVerification;
                if ($kyc) {
                    $kyc->user_id = $user->id;
                    $kyc->document_type = isset($request->document_type) ? $request->document_type : (isset($kyc->document_type) ? $kyc->document_type : '');
                    $kyc->document_number = isset($request->document_number) ? $request->document_number : (isset($kyc->document_number) ? $kyc->document_number : '');

                    $kyc_upload = '';
                    if ($request->hasFile('document_file')) {
                        $upload_dir = "/uploads/landlord/kyc";
                        $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('document_file'), 'public');
                        $kyc_upload = Storage::disk('digitalocean')->url($name);

                        //remove old kyc
                        $oldkyc = $kyc->document_upload;
                        if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldkyc))) {
                            Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldkyc));
                        }
                    }
                    $kyc->document_upload = !empty($kyc_upload) ? $kyc_upload : (isset($kyc->document_upload) ? $kyc->document_upload : '');
                    if ($address->save() && $kyc->save()) {
                        return response([
                            'status'    => true,
                            'message'   => 'Landlord\'s information updated successfully.',
                            'user'      => $user
                        ], 200);
                    }
                }
            }
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $landlord = User::where('role', 'landlord')->find($id);
        if ($landlord) {
            //remove files from the server
            $upload_dir = "/uploads/landlord/profile_pic";
            $oldimg = $landlord->profile_pic;
            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
            }

            //remove address if not removed automatically
            $address = Address::find($landlord->address_id);
            if ($address) {
                $address->delete();
            }
            //remove kyc
            $kyc = KycVerification::find($landlord->kyc_id);
            if ($kyc) {
                $upload_dir = "/uploads/landlord/kyc";
                $oldkyc = $kyc->document_upload;
                if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldkyc))) {
                    Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldkyc));
                }
                $kyc->delete();
            }

            $landlord->delete();
            return response([
                'status'  => true,
                'message' => 'Landlord deleted successfully.',
                'data'    => $landlord
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'Landlord not found.'
        ], 404);
    }
}
