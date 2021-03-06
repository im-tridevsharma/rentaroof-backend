<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Agreement;
use App\Models\Property;
use App\Models\IboRating;
use App\Models\KycVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class IBOManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //fetch ibos
        $users = User::where("role", "ibo");
        if ($request->type && !empty($request->type)) {
            $users->where("account_status", $request->type);
        }
        $users = $users->get();

        return response([
            'status'  => true,
            'message' => 'IBOs fetched successfully.',
            'data'    => $users->load('kyc')
        ], 200);
    }

    /**
     * Return no. of ibos
     */
    public function total()
    {
        $total = User::where("role", "ibo")->count();
        $rented = Agreement::where("property_id", "!=", null)->count();

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $properties_data = [
            "verified" => [],
            "notverified" => []
        ];

        foreach ($months as $m) {
            $vcount = Property::where("is_approved", 1)->whereMonth("created_at", date('m', strtotime($m)))->count();
            $properties_data['verified'][$m] = $vcount;
            $nvcount = Property::where("is_approved", 0)->whereMonth("created_at", date('m', strtotime($m)))->count();
            $properties_data['notverified'][$m] = $nvcount;
        }

        if ($total >= 0) {
            return response([
                "status"    => true,
                "message"   => "Feteched successfully.",
                "data"      => [
                    "ibos" => $total,
                    "rented" => $rented,
                    "properties_stat" => $properties_data
                ]
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => "Something went wrong!"
        ], 500);
    }

    //ban ibo
    public function ban($id)
    {
        $ibo = User::where("role", "ibo")->where("id", $id)->first();
        if ($ibo) {
            $ibo->account_status = "banned";
            $ibo->save();

            return response([
                'status'    => true,
                'message'   => "Ibo banned successfully.",
                'data'      => $ibo->load('kyc')
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => "Ibo not found!"
        ], 404);
    }

    //activate ibo
    public function activate($id)
    {
        $ibo = User::where("role", "ibo")->where("id", $id)->first();
        if ($ibo) {
            $ibo->account_status = "activated";
            $ibo->save();

            return response([
                'status'    => true,
                'message'   => "Ibo activated successfully.",
                'data'      => $ibo->load('kyc')
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => "Ibo not found!"
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
            $upload_dir = "/uploads/ibos/profile_pic";
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('profile_pic'), 'public');
            $profile_pic_url = Storage::disk('digitalocean')->url($name);
        }

        $user = new User;
        $user->role = "ibo";
        $user->system_userid = 'IID-0' . rand(11111, 99999);
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
                $upload_dir = "/uploads/ibos/kyc";
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
                'message'   => 'New IBO added successfully.',
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
        $ibo = User::where('role', 'ibo')->find($id);

        if ($ibo) {
            return response([
                'status'  => true,
                'message' => 'IBO fetched successfully.',
                'data'    => $ibo->load(['address', 'kyc'])
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'IBO not found.'
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
            $upload_dir = "/uploads/ibos/profile_pic";
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('profile_pic'), 'public');
            $profile_pic_url = Storage::disk('digitalocean')->url($name);

            //remove old image
            $oldimg = $user->profile_pic;
            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
            }
        }

        $user->role = "ibo";
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
                    $kyc->document_type = isset($request->document_type) ? $request->document_type : '';
                    $kyc->document_number = isset($request->document_number) ? $request->document_number : '';

                    $kyc_upload = '';
                    if ($request->hasFile('document_file')) {
                        $upload_dir = "/uploads/ibos/kyc";
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
                            'message'   => 'IBO\'s information updated successfully.',
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
        $ibo = User::where('role', 'ibo')->find($id);
        if ($ibo) {
            //remove files from the server
            $upload_dir = "/uploads/ibos/profile_pic";
            $oldimg = $ibo->profile_pic;
            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
            }

            //remove address if not removed automatically
            $address = Address::find($ibo->address_id);
            if ($address) {
                $address->delete();
            }
            //remove kyc
            $kyc = KycVerification::find($ibo->kyc_id);
            if ($kyc) {
                $upload_dir = "/uploads/ibos/kyc";
                $oldkyc = $kyc->document_upload;
                if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldkyc))) {
                    Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldkyc));
                }
                $kyc->delete();
            }

            $ibo->delete();
            return response([
                'status'  => true,
                'message' => 'IBO deleted successfully.',
                'data'    => $ibo
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'IBO not found.'
        ], 404);
    }

    //get ibo reviews
    public function reviews($id)
    {
        $ibo = User::find($id);
        if ($ibo && $ibo->role === 'ibo') {
            $reviews = IboRating::where("ibo_id", $ibo->id)->get();
            return response([
                'status' => true,
                'message' => 'Rating and Reviews.',
                'data'   => $reviews
            ]);
        } else {
            return response([
                'status'    => false,
                'message'   => 'Ibo not found.'
            ]);
        }
    }


    //bulk action
    public function bulk_action(Request $request)
    {
        if ($request->has('action') && $request->has('ids')) {
            if (is_array($request->ids)) {
                foreach ($request->ids as $id) {
                    switch ($request->action) {
                        case 'mark-activated':
                            $user = User::where("role", "ibo")->find($id);
                            if ($user) {
                                $user->account_status = 'activated';
                                $user->save();
                            }
                            break;
                        case 'mark-banned':
                            $user = User::find($id);
                            if ($user) {
                                $user->account_status = 'banned';
                                $user->save();
                            }
                            break;
                        case 'mark-deactivated':
                            $user = User::find($id);
                            if ($user) {
                                $user->account_status = 'deactivated';
                                $user->save();
                            }
                            break;
                        case 'mark-not-verified':
                            $user = User::find($id);
                            if ($user) {
                                $user->account_status = 'not-verified';
                                $user->save();
                            }
                            break;
                        case 'remove':
                            $res = $this->destroy($id);
                            break;
                        default:
                            return response([
                                'status' => false,
                                'message'   => 'Action not matched.'
                            ], 404);
                    }
                }

                return response([
                    'status'    => true,
                    'message'   => 'Action performed successfully.'
                ], 200);
            }
        } else {
            return response([
                'status'    => false,
                'message'   => 'Please select records to perform actions.'
            ], 422);
        }
    }
}
