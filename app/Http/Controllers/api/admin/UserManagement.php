<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class UserManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //fetch users
        $users = User::where("role", "tenant")->get();

        return response([
            'status'  => true,
            'message' => 'Users fetched successfully.',
            'data'    => Crypt::encryptString($users)
        ], 200);
    }


    /**
     * Return no. of users
     */
    public function total()
    {
        $total = User::where("role", "tenant")->count();
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

    //ban user
    public function ban($id)
    {
        $user = User::where("role", "tenant")->where("id", $id)->first();
        if ($user) {
            $user->account_status = "banned";
            $user->save();

            return response([
                'status'    => true,
                'message'   => "User banned successfully.",
                'data'      => $user->load('kyc')
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => "User not found!"
        ], 404);
    }

    //activate user
    public function activate($id)
    {
        $user = User::where("role", "tenant")->where("id", $id)->first();
        if ($user) {
            $user->account_status = "activated";
            $user->save();

            return response([
                'status'    => true,
                'message'   => "User activated successfully.",
                'data'      => $user->load('kyc')
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => "User not found!"
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
            $upload_dir = "/uploads/users/profile_pic";
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('profile_pic'), 'public');
            $profile_pic_url = Storage::disk('digitalocean')->url($name);
        }

        $user = new User;
        $user->role = "tenant";
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

            if ($address->save()) {
                $user->address_id = $address->id;
                $user->save();
            }

            return response([
                'status'    => true,
                'message'   => 'New User added successfully.',
                'user'      => Crypt::encryptString($user)
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
        $user = User::where('role', 'tenant')->find($id);

        if ($user) {
            return response([
                'status'  => true,
                'message' => 'User fetched successfully.',
                'data'    => Crypt::encryptString($user->load('address'))
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'User not found.'
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
            'password'  => 'min:6',
            'profile_pic' => 'mimes:png,jpg,jpeg|max:2048'
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
            $upload_dir = "/uploads/users/profile_pic";
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('profile_pic'), 'public');
            $profile_pic_url = Storage::disk('digitalocean')->url($name);

            //remove old image
            $oldimg = $user->profile_pic;
            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
            }
        }

        if ($user) {
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
                    $address->landmark = isset($request->landmark) ? $request->landmark : '';
                    $address->house_number = isset($request->houseno) ? $request->houseno : '';
                    $address->country = $request->country;
                    $address->state = $request->state;
                    $address->city = $request->city;
                    $address->pincode = isset($request->pincode) ? $request->pincode : '';
                    $address->full_address = isset($request->fulladdress) ? $request->fulladdress : '';

                    $address->save();

                    return response([
                        'status'    => true,
                        'message'   => 'User\'s information updated successfully.',
                        'user'      => Crypt::encryptString($user)
                    ], 200);
                }
            }
        }

        return response([
            'status'    => false,
            'message'   => 'User not found!'
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
        $user = User::where('role', 'tenant')->find($id);
        if ($user) {
            //remove files from the server
            $upload_dir = "/uploads/users/profile_pic";
            $oldimg = $user->profile_pic;
            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
            }

            //remove address if not removed automatically
            $address = Address::find($user->address_id);
            if ($address) {
                $address->delete();
            }

            $user->delete();
            return response([
                'status'  => true,
                'message' => 'User deleted successfully.',
                'data'    => Crypt::encryptString($user)
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'User not found.'
        ], 404);
    }
}
