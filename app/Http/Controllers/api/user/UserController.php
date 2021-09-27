<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
        $validator = Validator::make($request->all(), [
            'first'        => 'required|string|between:2,25',
            'email'        => 'required|email',
            'mobile'       => 'required|digits_between:10,12',
            'gender'       => 'required|in:male,female,other',
            'profile_pic'  => 'mimes:jpg,png,jpeg|max:2048'
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
            $user->first = $request->first;
            $user->last  = isset($request->last) ? $request->last : '';
            $user->email = isset($request->email) ? $request->email : '';
            $user->mobile = isset($request->mobile) ? $request->mobile : '';
            $user->gender = isset($request->gender) ? $request->gender : '';
            $user->username = isset($request->username) ? $request->username : NULL;
            $user->dob = !empty($request->dob) ? date("Y-m-d", strtotime($request->dob)) : NULL;
            $user->profile_pic = !empty($profile_pic_url) ? $profile_pic_url : $user->profile_pic;

            if ($user->save()) {
                //save address
                $address = $user->address_id ? Address::find($user->address_id) : new Address;
                if ($address) {
                    $address->user_id = $user->id;
                    $address->landmark = isset($request->landmark) ? $request->landmark : (isset($address->landmark) ? $address->landmark : '');
                    $address->house_number = isset($request->house_number) ? $request->house_number : (isset($address->house_number) ? $address->house_number : '');
                    $address->full_address = isset($request->full_address) ? $request->full_address : (isset($address->full_address) ? $address->full_address : '');
                    $address->country = isset($request->country) ? $request->country : (isset($address->country) ? $address->country : NULL);
                    $address->state = isset($request->state) ? $request->state : (isset($address->state) ? $address->state : NULL);
                    $address->city = isset($request->city) ? $request->city : (isset($address->city) ? $address->city : NULL);
                    $address->pincode = isset($request->pincode) ? $request->pincode : (isset($address->pincode) ? $address->pincode : '');

                    if ($address->save()) {
                        $user->address_id = $address->id;
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

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
