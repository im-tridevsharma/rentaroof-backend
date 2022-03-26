<?php

namespace App\Http\Controllers\api;


use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\OTPVerification;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\User;
use App\Models\Wallet;
use Exception;
use FFI\Exception as FFIException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('jwt.verify', ['except' => ['login', 'signup', 'profileByCode', 'sendOtp', 'sendOtpEmail', 'mobileVerify', 'emailVerify', 'createNewPassword']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function profileByCode($code)
    {
        $user = User::where("system_userid", $code)->first();
        $load = ['address'];
        if ($user->role !== 'tenant') {
            array_push($load, 'kyc');
        }
        return response([
            'status'    => true,
            'message'   => 'User datails fetched successfully.',
            'data'      =>  $user->load($load)
        ], 200);
    }

    public function login(Request $request)
    {
        $isMobileUser = false;
        if ($request->has('password') && !empty($request->password)) {
            $rules = [
                'password' => 'string|min:8'
            ];
        }

        $errorMessages = [
            'required' => 'The :attribute field can not be blank.'
        ];

        if (is_numeric($request->email)) {
            $rules['email'] = 'required|digits:10';
            $errorMessages['email.digits'] = 'Mobile number is not valid (must be of 10 digits).';
            $isMobileUser = true;
        } else {
            $rules['email'] = 'required|email';
        }

        $validator = Validator::make($request->all(), $rules, $errorMessages);

        if ($validator->fails()) {
            return response([
                'message' => "Some errors occured.",
                'error' => $validator->errors()
            ], 400);
        }

        $user = null;

        if ($request->has('otp') && !empty($request->otp)) {
            //check for otp auth
            $user = $isMobileUser ? User::where("mobile", $request->email)->first() : User::where("email", $request->email)->first();
            if ($user) {
                $sent_otp = OTPVerification::where("user_id", $user->id)->where("OTP", $request->otp)->where("is_expired", 0)->first();

                if ($sent_otp && date("Y-m-d H:i:s", strtotime($sent_otp->expired_at)) < date('Y-m-d H:i:s')) {

                    return response([
                        'status'    => false,
                        'message'   => 'OTP has been expired.'
                    ], 401);
                }

                if (!$sent_otp || $sent_otp->OTP !== $request->otp) {
                    return response([
                        'status'    => false,
                        'message'   => 'OTP is invalid. Please check once.'
                    ], 401);
                }

                $sent_otp->is_expired = 1;
                $sent_otp->save();
            } else {
                return response([
                    'status'    => false,
                    'message'   => $isMobileUser ? 'Mobile number is not authorized!' : 'Email address is not authorized!'
                ], 404);
            }
        }

        $credentials = ["password" => $request->password];
        if ($isMobileUser) {
            $credentials['mobile'] = $request->email;
        } else {
            $credentials['email'] = $request->email;
        }

        $user = $isMobileUser ? User::where("mobile", $request->email)->first() : User::where("email", $request->email)->first();
        if ($user && $user->account_status === 'banned') {
            return response([
                'status'  => false,
                'message' => "Your account has been banned. Contact to Administrator.",
            ], 401);
        }

        if ($user && $user->account_status === 'not-verified') {
            return response([
                'status'  => false,
                'message' => "Your account is not verified yet.",
            ], 401);
        }

        $token = null;

        if (!$request->has('otp') || empty($request->otp)) {
            try {
                if (!$token = JWTAuth::attempt($credentials)) {
                    return response([
                        'message' => $isMobileUser ? "Mobile or Password is wrong!" : "Email or Password is wrong!"
                    ], 401);
                }
            } catch (Exception $e) {
                return response([
                    'status'    =>  false,
                    'message'   => 'Some errors occured.',
                    'error'     => [$e->errorInfo[count($e->errorInfo) - 1]]
                ], 500);
            }
        } else {
            try {
                if (!$token = JWTAuth::fromUser($user)) {
                    return response([
                        'message' => $isMobileUser ? "Mobile or Password is wrong!" : "Email or Password is wrong!"
                    ], 401);
                }
            } catch (Exception $e) {
                return response([
                    'status'    =>  false,
                    'message'   => 'Some errors occured.',
                    'error'     => [$e->errorInfo[count($e->errorInfo) - 1]]
                ], 500);
            }
        }

        if ($request->filled('remember_me') && ($request->remember_me === 'yes' || $request->remember_me)) {
            JWTAuth::factory()->setTTL(60 * 24 * 356);
        }

        $user = $user ? $user : JWTAuth::user();
        $user->is_logged_in = 1;

        //before_login_added
        $is_property_updated = false;
        if ($request->before_login_added) {
            $code = explode("_", $request->before_login_added);
            $property = Property::find($code[1]);
            if ($property && $user->role !== 'tenant') {
                $property->posted_by = $user->id;
                if ($user->role === 'landlord') {
                    $property->landlord = $user->id;
                }

                $property->save();
                $is_property_updated = true;
            }
        }

        $user->device_token = $request->device_token ?? '';

        $user->save();
        $info = [
            'id'       => $user->id,
            'system_userid' => $user->system_userid,
            'first'    => $user->first,
            'last'    => $user->last,
            'fullname' => $user->first . ' ' . $user->last,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'role' => $user->role,
            'profile_pic' => $user->profile_pic,
            'permissions' => [],
            'account_status'  => $user->account_status,
            'deactivate_reason' => $user->deactivate_reason,
            'is_property_updated' => $is_property_updated
        ];
        return $this->respondWithToken($token, $info);
    }

    /*
    User sign up from website
    */

    public function signup(Request $request)
    {
        $isMobileUser = false;

        $rules = [
            'name'      => 'required|string|between:2,50',
            'role'      => 'required|string|in:tenant,ibo,landlord',
            'email'     => 'required|email|unique:users',
            'mobile'    => 'required',
            'password'  => 'required|string|min:8'
        ];

        $errorMessages = [
            'required' => 'The :attribute field can not be blank.'
        ];

        if (is_numeric($request->mobile)) {
            $rules['mobile'] = 'required|digits:10';
            $errorMessages['mobile.digits'] = 'Mobile number is not valid (must be of 10 digits).';
            $isMobileUser = true;
        }

        $validator = Validator::make($request->all(), $rules, $errorMessages);

        if ($validator->fails()) {
            return response([
                'message' => 'Some errors occured',
                'error'   => $validator->errors()
            ], 400);
        }

        //check if already a user with mobile
        if ($isMobileUser && User::where('mobile', $request->mobile)->count()) {
            return response([
                'message' => 'Some errors occured',
                'error' => ['mobile' => 'The Mobile number has already been taken.']
            ]);
        }

        //arrange data to save
        $user = new User;

        $name = explode(" ", $request->name);
        $user->first = isset($name[0]) ? $name[0] : $request->name;
        $user->last = isset($name[1]) ? $name[1] : '';
        $user->email = $request->email ?? '';
        $user->mobile = $request->mobile ?? '';
        $user->role = $request->role;
        $user->password = bcrypt($request->password);
        $user->referral_code = $request->referral_code;
        $user->account_status = 'not-verified';
        $user->email_verified = 0;
        $user->mobile_verified = 0;
        $user->system_ip = $request->ip();
        $user->category = $request->filled('category') ? $request->category : NULL;

        //generate new userid for user
        $cid = ["tenant" => "UID-0", "ibo" => "IID-0", "landlord" => "LID-0"];
        $user->system_userid = $cid[$request->role] . rand(11111, 99999);

        //save user to database
        if ($user->save()) {
            $botp = rand(111111, 999999);
            $otp_data = [
                'user'  => $user->first . ' ' . $user->last,
                'otp'   => $botp,
                'email' => $user->email
            ];

            //send otp to mobile
            $motp = rand(111111, 999999);
            $otp = 'Verify your mobile number with Rent A Roof. OTP for verification is - ' . $motp;
            $is_otp = sms($otp, $user->mobile);
            if ($is_otp) {
                //save this in database
                $dbotp = new OTPVerification;
                $dbotp->txn_id = $is_otp;
                $dbotp->user_id = $user->id;
                $dbotp->OTP = $motp;
                $dbotp->sent_for = "mobile_verification";
                $dbotp->expired_at = Carbon::now()->addMinutes(10)->format('Y-m-d H:i:s');
                $dbotp->save();
            } else {
                $user->delete();
                return response([
                    'status'  => false,
                    'message' => 'Unable to send otp to your mobile! Please check your mobile number.'
                ]);
            }

            return response([
                'status' => true,
                'message' => 'OTP has been sent on your mobile for Verification. Please verify!',
                'user'   => $user->only('id', 'email', 'mobile')
            ], 200);
        } else {
            return response([
                'status' => false,
                'message' => 'Unable to register user.'
            ], 500);
        }
    }

    //verify email otp
    public function emailVerify(Request $request)
    {
        $user_id = $request->user_id ?? false;
        $otp     = $request->otp ?? '';

        $user = User::find($user_id);

        if ($user_id && $otp && $user) {
            $votp = OTPVerification::where("user_id", $user_id)
                ->where('sent_for', $request->has('forgotpass') ? 'email_otp_login' : 'email_verification')
                ->where("OTP", $otp)->where("is_expired", 0)->first();

            if ($votp && date("Y-m-d H:i:s", strtotime($votp->expired_at)) < date('Y-m-d H:i:s')) {
                return response([
                    'status'    => false,
                    'message'   => 'OTP has been expired.'
                ], 200);
            }

            if ($votp && $votp->OTP === $otp) {

                $votp->is_expired = 1;
                $votp->save();

                $res = [
                    'status'    => true,
                    'message'   => $request->has('forgotpass') ? 'Create Your New Password. Session is only valid for 5 minutes.' : 'Email Verified! Enter OTP sent on your mobile.'
                ];

                if ($request->has('forgotpass')) {
                    $res['_token'] = encrypt(['user' => $user->id, 'expires' => Carbon::now()->addMinutes(5)->format('Y-m-d H:i:s')], 1);
                }

                return response($res, 200);
            } else {
                return response([
                    'status'    => false,
                    'message'   => 'OTP doesn\'t match.'
                ], 401);
            }
        } else {
            return response([
                'status'    => false,
                'message'   => 'Please check your OTP. It seems invalid.'
            ], 422);
        }
    }

    //verify mobile otp
    public function mobileVerify(Request $request)
    {
        $user_id = $request->user_id ?? false;
        $otp     = $request->otp ?? '';

        $user = User::find($user_id);

        if ($user_id && $otp && $user) {
            $votp = OTPVerification::where("user_id", $user_id)->where("OTP", $otp)->where("is_expired", 0)->first();
            if ($votp && date("Y-m-d H:i:s", strtotime($votp->expired_at)) < date('Y-m-d H:i:s')) {
                return response([
                    'status'    => false,
                    'message'   => 'OTP has been expired.'
                ], 200);
            }
            if ($votp && $votp->OTP === $otp) {

                if (!$request->has('forgotpass')) {
                    $this->userTools($user);
                    $user->email_verified = 0;
                    $user->mobile_verified = 1;
                    $user->account_status = "activated";
                    $user->save();
                }

                $votp->is_expired = 1;
                $votp->save();

                $res = [
                    'status'    => true,
                    'message'   => $request->has('forgotpass') ? 'Create Your New Password. Session is only valid for 5 minutes.' : 'Mobile Verified. Redirecting...'
                ];

                if ($request->has('forgotpass')) {
                    $res['_token']  = encrypt(['user' => $user->id, 'expires' => Carbon::now()->addMinutes(5)->format('Y-m-d H:i:s')], 1);
                } else {
                    $res['token'] = JWTAuth::fromUser($user);
                    $res['user']  = $user;
                }

                return response($res, 200);
            } else {
                return response([
                    'status'    => false,
                    'message'   => 'OTP doesn\'t match.'
                ], 401);
            }
        } else {
            return response([
                'status'    => false,
                'message'   => 'Please check your OTP. It seems invalid.'
            ], 422);
        }
    }

    public function userTools($user)
    {
        //create settings if user is ibo and landlord
        if ($user->role !== 'tenant') {
            $settings_keys = [
                "account_notification",
                "receive_important_updates_on_number",
                "meeting_updates",
                "offers_and_updates"
            ];

            foreach ($settings_keys as $setting_key) {
                $setting = DB::table('user_settings')->where("key", $setting_key)->where("user_id", $user->id)->first();
                if ($setting) {
                    //update
                    DB::table('user_settings')->where("user_id", $user->id)->where("key", $setting_key)
                        ->update(["value" => 'no']);
                } else {
                    //save
                    DB::table('user_settings')
                        ->insert(["key" => $setting_key, "value" => 'no', "user_id" => $user->id]);
                }
            }
        }

        //create user wallet
        $wallet = new Wallet;
        $wallet->user_id = $user->id;
        $wallet->amount = 0;
        $wallet->credit = 0;
        $wallet->debit = 0;
        $wallet->last_transaction_type = 'credit';
        $wallet->last_credit_transaction = date('Y-m-d H:i:s');

        $wallet->save();

        if ($user->referral_code) {
            $refuser = User::where("system_userid", $user->referral_code)->first();
            if ($refuser) {
                //get settings for points
                $point_value  = DB::table('settings')->where("setting_key", "point_value")->first()->setting_value;
                $s_point  = DB::table('settings')->where("setting_key", "referral_bonus_sender_point")->first()->setting_value;
                $r_point = DB::table('settings')->where("setting_key", "referral_bonus_receiver_point")->first()->setting_value;

                $spoints = floatval($s_point) * floatval($point_value);
                $rpoints = floatval($r_point) * floatval($point_value);

                //point data
                $sdata = [
                    "user_id"   => $refuser->id,
                    "role"      => $refuser->role,
                    "title"     => "You earned " . $s_point . " points for referral of " . $user->first . " " . $user->last,
                    "point_value"   => $point_value,
                    "points"    => $s_point,
                    "amount_earned" => $spoints,
                    "type"  => "credit",
                    "for"  => "referral",
                    "created_at"    => date("Y-m-d H:i:s"),
                    "updated_at"    => date("Y-m-d H:i:s"),
                ];

                DB::table('user_referral_points')->insert($sdata);

                //point data
                $rdata = [
                    "user_id"   => $user->id,
                    "role"      => $user->role,
                    "title"     => "You earned " . $r_point . " points for referral by " . $refuser->first . " " . $refuser->last,
                    "point_value"   => $point_value,
                    "points"    => $r_point,
                    "amount_earned" => $rpoints,
                    "type"  => "credit",
                    "for"  => "referred",
                    "created_at"    => date("Y-m-d H:i:s"),
                    "updated_at"    => date("Y-m-d H:i:s"),
                ];

                DB::table('user_referral_points')->insert($rdata);
            }
        }
    }

    //sendOtp
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'mobile'    => 'required|digits:10'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Mobile number is not valid.',
                'error'     => $validator->errors()
            ], 422);
        }

        //check is there any user with this mobile number
        $user = User::where("mobile", $request->mobile)->first();

        if ($user) {
            $botp = rand(111111, 999999);
            $otp = 'OTP for Rent a Roof is - ' . $botp;
            $is_otp = sms($otp, $user->mobile);

            if ($is_otp) {
                //save this in database
                $dbotp = new OTPVerification;
                $dbotp->txn_id = $is_otp;
                $dbotp->user_id = $user->id;
                $dbotp->OTP = $botp;
                $dbotp->sent_for = "otp_login";
                $dbotp->expired_at = Carbon::now()->addMinutes(10)->format('Y-m-d H:i:s');
                $dbotp->save();

                return response([
                    'status'    => true,
                    'message'   => 'OTP Sent sucessfully.',
                    'user'      => $user->only('id', 'email', 'mobile')
                ], 200);
            } else {
                return response([
                    'status'    => false,
                    'message'   => 'Something went wrong. Please check your mobile number.',
                ], 500);
            }
        } else {
            return response([
                'status'    => false,
                'message'   => 'User not found with this mobile number.',
            ], 404);
        }
    }

    //sendOtpEmail
    public function sendOtpEmail(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'email'    => 'required|email'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Email is not valid.',
                'error'     => $validator->errors()
            ], 422);
        }

        //check is there any user with this mobile number
        $user = User::where("email", $request->email)->first();

        if ($user) {
            $botp = rand(111111, 999999);
            $otp_data = [
                "user"  => $user->first . ' ' . $user->last,
                "otp"   => $botp,
                "email" => $user->email
            ];

            $is_otp = send_email_otp($otp_data);

            if ($is_otp) {
                //save this in database
                $dbotp = new OTPVerification;
                $dbotp->txn_id = time();
                $dbotp->user_id = $user->id;
                $dbotp->OTP = $botp;
                $dbotp->sent_for = "email_otp_login";
                $dbotp->expired_at = Carbon::now()->addMinutes(10)->format('Y-m-d H:i:s');
                $dbotp->save();

                return response([
                    'status'    => true,
                    'message'   => 'OTP Sent sucessfully.',
                    'user'      => $user->only('id', 'email', 'mobile')
                ], 200);
            } else {
                return response([
                    'status'    => false,
                    'message'   => 'Something went wrong. Please check your email address.',
                ], 500);
            }
        } else {
            return response([
                'status'    => false,
                'message'   => 'User not found with this email address.',
            ], 404);
        }
    }

    //create new password
    public function createNewPassword(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'new_password'      => 'required|min:8|max:50',
            'confirm_password'  => 'required|min:8|max:50',
            '_token'            => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Please check your details.',
                'error'     => $validator->errors()
            ], 422);
        }

        if ($request->new_password !== $request->confirm_password) {
            return response([
                'status'    => false,
                'message'   => 'New Password and Confirm Password is not same.'
            ], 422);
        }

        //check if token is expired or not
        try {
            $token = decrypt($request->_token, 1);
            if (date("Y-m-d H:i:s", strtotime($token['expires'])) < date('Y-m-d H:i:s')) {
                return response([
                    'status'    => false,
                    'message'   => 'Session has been expired!'
                ], 200);
            }

            $user = User::find($token['user']);
            if ($user) {

                $user->password = Hash::make($request->new_password);
                $user->save();

                return response([
                    'status'    => true,
                    'message'   => 'You successfully changed your password.'
                ], 200);
            } else {
                return response([
                    'status'    => false,
                    'message'   => 'You are not authorized to change password.'
                ], 401);
            }
        } catch (FFIException $e) {
            return response([
                'status'    => false,
                'message'   => $e->getMessage()
            ], 500);
        }
    }

    public function profile(Request $request)
    {
        $user = true;
        if ($request->input('mode')) {
            $user = JWTAuth::user()->load(['address', 'kyc']);
        }

        return response([
            'status' => true,
            'user'   => $user
        ], 200);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        //Request is validated, do logout
        try {
            $user = JWTAuth::user();
            $user->is_logged_in = 0;
            $user->last_logged_in = date("Y-m-d H:i:s");
            $user->save();

            JWTAuth::invalidate();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully.'
            ]);
        } catch (JWTException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry, something went wrong!'
            ], 500);
        }
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(JwtAuth::refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token, $info = null)
    {
        $cookie = $this->getCookie($token);
        $json = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60
        ];

        if ($info) {
            $json['user'] = $info;
        }

        return response()->json($json);
    }

    protected function getCookie($token)
    {
        return cookie(
            'auth_token',
            $token,
            JWTAuth::factory()->getTTL(),
            '/',
            null,
            null,
            true,
            true,
            false
        );
    }

    public function isSOS()
    {
        $user = JWTAuth::user();
        if ($user) {
            $meetings = Meeting::where("user_id", $user->id)->orWhere("created_by_id", $user->id)->orderBy("id", "desc");
            if ($user && $user->role === 'tenant') {
                $meetings->where("meeting_status", '!=', 'pending');
                $meetings->where("user_id", '!=', 0);
            }

            $meetings = $meetings->get();
            $today = 0;

            foreach ($meetings as $m) {
                if (date('Y-m-d') === date('Y-m-d', strtotime($m->start_time))) {
                    $today++;
                }
            }

            return response([
                'status'    => true,
                'data'  => $today > 0 ? 'yes' : 'no'
            ], 200);
        } else {
            return response([
                'status'    => false,
                'message'   => 'User not found'
            ], 401);
        }
    }
}
