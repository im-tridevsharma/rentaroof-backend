<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Country;
use App\Models\IboEarning;
use App\Models\LandlordEarning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //fetch users
        $users = User::where("role", "tenant");
        if ($request->type && !empty($request->type)) {
            $users->where("account_status", $request->type);
        }
        $users = $users->get();

        return response([
            'status'  => true,
            'message' => 'Users fetched successfully.',
            'data'    => Crypt::encryptString($users)
        ], 200);
    }

    //users_for_tracking
    public function users_for_tracking()
    {
        $users = User::select(['id', 'first', 'last', 'profile_pic', 'email', 'mobile', 'address_id', 'system_userid', 'role', 'is_logged_in'])->where("account_status", "activated")->get()->map(function ($q) {
            $address = Address::find($q->address_id);
            if ($address) {
                $uaddr = [
                    "full_address" => $address->full_address ?? '',
                    "lat"          => $address->lat ?? 0,
                    "long"         => $address->long ?? 0,
                    "country"      => Country::find($address->country)->name ?? '',
                    "state"        => Country::find($address->state)->name ?? '',
                    "city"         => Country::find($address->city)->name ?? '',
                ];

                $q->address = $uaddr;
            }
            return $q;
        });

        return response([
            'status'    => true,
            'message'   => 'Users fetched successfully.',
            'data'      => $users
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
        $user->system_userid = 'UID-0' . rand(11111, 99999);
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
                'data'    => Crypt::encryptString($user->load('address', 'kyc'))
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
            $upload_dir2 = "/uploads/users/signature";

            $oldimg = $user->profile_pic;
            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
            }

            $oldimg2 = $user->signature;
            if (Storage::disk('digitalocean')->exists($upload_dir2 . '/' . basename($oldimg2))) {
                Storage::disk('digitalocean')->delete($upload_dir2 . '/' . basename($oldimg2));
            }

            //remove address if not removed automatically
            $address = Address::find($user->address_id);
            if ($address) {
                $address->delete();
            }

            //delete wallet
            Wallet::where("user_id", $user->id)->delete();

            //delete settings
            DB::table('user_settings')->where("user_id", $user->id)->delete();

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

    //bulk action
    public function bulk_action(Request $request)
    {
        if ($request->has('action') && $request->has('ids')) {
            if (is_array($request->ids)) {
                foreach ($request->ids as $id) {
                    switch ($request->action) {
                        case 'mark-activated':
                            $user = User::find($id);
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

    //wallet_payout
    public function wallet_payout()
    {
        $requests = DB::table('wallet_payouts')->orderBy('id', 'desc')->get()->map(function ($q) {
            $user = User::find($q->user_id);
            $q->name = $user->first . ' ' . $user->last;
            $q->email = $user->email;
            return $q;
        });
        return response([
            'status'    => true,
            'message'   => 'Payouts fetched successfully.',
            'data'      => $requests
        ]);
    }

    public function view_wallet_payout($id)
    {
        $request = DB::table('wallet_payouts')->where("id", $id)->first();

        $user = User::find($request->user_id);
        $request->name = $user->first . ' ' . $user->last;
        $request->email = $user->email;

        $wallet = Wallet::where("user_id", $user->id)->first();
        $request->wallet = $wallet;

        return response([
            'status'    => true,
            'message'   => 'Payout fetched successfully.',
            'data'      => $request
        ], 200);
    }

    public function release_payout(Request $request, $id)
    {
        $amount = $request->amount ?? 0;
        $txn = $request->transaction_id ?? '';
        $txn_status = $request->transaction_status ?? 'pending';

        if ($amount && $txn) {
            $payout = DB::table('wallet_payouts')->where("id", $id)->first();
            $wallet = Wallet::where("user_id", $payout->user_id)->first();

            if ($payout && $wallet) {
                if ($wallet->amount < $amount) {
                    return response([
                        'status'    => false,
                        'message'   => 'Insufficient amount is available.'
                    ], 200);
                }

                //update payout
                DB::table('wallet_payouts')->where("id", $id)->update([
                    'payout_status' => $txn_status === 'paid' ? 'paid' : 'accepted',
                    'transaction_id' => $txn,
                    'transaction_status' => $txn_status,
                    'payout_amount' => $amount
                ]);

                //if success
                if ($txn_status === 'paid') {
                    $wallet->amount -= floatval($amount);
                    $wallet->debit += floatval($amount);
                    $wallet->save();
                }

                return response([
                    'status'    => true,
                    'message'   => 'Payout Done',
                ], 200);
            } else {
                return response([
                    'status'    => false,
                    'message'   => 'Payout request is not valid.'
                ], 200);
            }
        } else {
            return response([
                'status'    => false,
                'message'   => 'Amount is not valid.'
            ], 200);
        }
    }

    public function delete_wallet_payout($id)
    {
        $req = DB::table('wallet_payouts')->where("id", $id)->first();
        DB::table('wallet_payouts')->where("id", $id)->delete();

        return response([
            'status'    => true,
            'message'   => 'Payout deleted successfully.',
            'data'      => $req
        ]);
    }

    //wallet_payout
    public function earning_payout()
    {
        $requests = DB::table('earning_payouts')->orderBy('id', 'desc')->get()->map(function ($q) {
            $user = User::find($q->user_id);
            $q->name = $user->first . ' ' . $user->last;
            $q->email = $user->email;
            return $q;
        });
        return response([
            'status'    => true,
            'message'   => 'Payouts fetched successfully.',
            'data'      => $requests
        ]);
    }

    public function view_earning_payout($id)
    {
        $request = DB::table('earning_payouts')->where("id", $id)->first();

        $user = User::find($request->user_id);
        $request->name = $user->first . ' ' . $user->last;
        $request->email = $user->email;
        if ($user->role === 'ibo') {
            $earning = IboEarning::where("ibo_id", $user->id)->get();
        } else {
            $earning = LandlordEarning::where("landlord_id", $user->id)->get();
        }

        $request->earning = $earning;

        return response([
            'status'    => true,
            'message'   => 'Payout fetched successfully.',
            'data'      => $request
        ], 200);
    }

    public function release_earning_payout(Request $request, $id)
    {
        $amount = $request->amount ?? 0;
        $txn = $request->transaction_id ?? '';
        $txn_status = $request->transaction_status ?? 'pending';

        if ($request->earnings && $txn) {
            $payout = DB::table('earning_payouts')->where("id", $id)->first();
            if ($request->earnings && count($request->earnings) > 0) {
                foreach ($request->earnings as $e) {
                    if ($payout) {
                        if ($payout->role === 'ibo') {
                            $e = IboEarning::find($e);
                        } else {
                            $e = LandlordEarning::find($e);
                        }
                        //update payout
                        DB::table('earning_payouts')->where("id", $id)->update([
                            'payout_status' => $txn_status === 'paid' ? 'paid' : 'accepted',
                            'transaction_id' => $txn,
                            'transaction_status' => $txn_status,
                            'payout_amount' => $amount
                        ]);

                        //if success
                        if ($txn_status === 'paid') {
                            $e->type = 'paid';
                            $e->save();
                        }
                    } else {
                        return response([
                            'status'    => false,
                            'message'   => 'Payout request is not valid.'
                        ], 200);
                    }
                }

                return response([
                    'status'    => true,
                    'message'   => 'Payout Done',
                ], 200);
            } else {
                return response([
                    'status'    => false,
                    'message'   => 'Please select earnings.'
                ], 400);
            }
        } else {
            return response([
                'status'    => false,
                'message'   => 'Amount is not valid.'
            ], 200);
        }
    }

    public function delete_earning_payout($id)
    {
        $req = DB::table('earning_payouts')->where("id", $id)->first();
        DB::table('earning_payouts')->where("id", $id)->delete();

        return response([
            'status'    => true,
            'message'   => 'Payout deleted successfully.',
            'data'      => $req
        ]);
    }
}
