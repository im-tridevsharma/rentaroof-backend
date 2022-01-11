<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OTPVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\User;
use App\Models\Wallet;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $this->middleware('jwt.verify', ['except' => ['login', 'signup', 'profileByCode','sendOtp','mobileVerify','emailVerify']]);
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
        if($request->has('password') && !empty($request->password)){
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

        if($request->has('otp') && !empty($request->otp)){
            //check for otp auth
            $user = User::where("mobile", $request->email)->first();
            if($user){
                $sent_otp = OTPVerification::where("user_id", $user->id)->where("OTP", $request->otp)->first(); 
                
                if($sent_otp && date("Y-m-d H:i:s", strtotime($sent_otp->expired_at)) < date('Y-m-d H:i:s')){
                    
                    return response([
                        'status'    => false,
                        'message'   => 'OTP has been expired.'
                    ], 401); 
                }

                if(!$sent_otp || $sent_otp->OTP !== $request->otp){
                    return response([
                        'status'    => false,
                        'message'   => 'OTP is invalid. Please check once.'
                    ], 401);
                }
            }else{
                return response([
                    'status'    => false,
                    'message'   => 'Mobile number is not authorized!'
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

        if(!$request->has('otp')){
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
        }else{
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

        if ($request->filled('remember_me') && $request->remember_me === 'yes') {
            JWTAuth::factory()->setTTL(60 * 24 * 356);
        }
        
        $user = $user ? $user : JWTAuth::user();
        $user->is_logged_in = 1;
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
            'account_status'  => $user->account_status
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
                'user'  => $user->first .' '. $user->last,
                'otp'   => $botp,
                'email' => $user->email
            ];

            $is_sent = send_email_otp($otp_data);
            if($is_sent == 1){
                //save this in database
                $dbotp = new OTPVerification;
                $dbotp->txn_id = time();
                $dbotp->user_id = $user->id;
                $dbotp->OTP = $botp;
                $dbotp->sent_for = "email_verification";
                $dbotp->expired_at = Carbon::now()->addMinutes(10)->format('Y-m-d H:i:s');
                $dbotp->save();
            }

            return response([
                'status' => true,
                'message'=> 'OTP has been sent on email for Email Verification. Please verify your email!',
                'user'   => $user->only('id','email','mobile')
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

        if($user_id && $otp && $user){
            $votp = OTPVerification::where("user_id", $user_id)->where("OTP", $otp)->first();
            if($votp && $votp->OTP === $otp){
                $botp = rand(111111, 999999);
                $otp = 'Verify your mobile number with Rent A Roof. OTP for verification is - '.$botp;
                $is_otp = sms($otp, $user->mobile);
                if($is_otp){
                    //save this in database
                    $dbotp = new OTPVerification;
                    $dbotp->txn_id = $is_otp;
                    $dbotp->user_id = $user->id;
                    $dbotp->OTP = $botp;
                    $dbotp->sent_for = "mobile_verification";
                    $dbotp->expired_at = Carbon::now()->addMinutes(10)->format('Y-m-d H:i:s');
                    $dbotp->save();
                }

                return response([
                    'status'    => true,
                    'message'   => 'Email Verified! OTP has been sent on your mobile. Please verify it.'
                ], 200);
            }else{
                return response([
                    'status'    => false,
                    'message'   => 'OTP doesn\'t match.'
                ], 401);
            }
        }else{
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

        if($user_id && $otp && $user){
            $votp = OTPVerification::where("user_id", $user_id)->where("OTP", $otp)->first();
            if($votp && $votp->OTP === $otp){
                $this->userTools($user);
                $user->email_verified = 1;
                $user->mobile_verified = 1;
                $user->account_status = "activated";
                $user->save();
                
                return response([
                    'status'    => true,
                    'message'   => 'Mobile Verified. Redirecting you to login page.'
                ], 200);
            }else{
                return response([
                    'status'    => false,
                    'message'   => 'OTP doesn\'t match.'
                ], 401);
            }
        }else{
            return response([
                'status'    => false,
                'message'   => 'Please check your OTP. It seems invalid.'
            ], 422); 
        }
    }

    public function userTools($user)
    {
        //create settings if user is ibo and landlord
        if($user->role !== 'tenant'){
            $settings_keys = [
                "account_notification",
                "receive_important_updates_on_number",
                "meeting_updates",
                "offers_and_updates"
            ];

            foreach($settings_keys as $setting_key){
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

        if($validator->fails()){
            return response([
                'status'    => false,
                'message'   => 'Mobile number is not valid.',
                'error'     => $validator->errors()
            ], 422);
        }

        //check is there any user with this mobile number
        $user = User::where("mobile", $request->mobile)->first();

        if($user){
            $botp = rand(111111, 999999);
            $otp = 'OTP for Rent a Roof is - '.$botp;
            $is_otp = sms($otp, $user->mobile);
            if($is_otp){
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
                ], 200);
            }else{
                return response([
                    'status'    => false,
                    'message'   => 'Something went wrong. Please check your mobile number.',
                ], 500);
            }
        }else{
            return response([
                'status'    => false,
                'message'   => 'User not found with this mobile number.',
            ], 404);
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
}
