<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\User;
use Exception;
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
        $this->middleware('jwt.verify', ['except' => ['login', 'signup', 'profileByCode']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function profileByCode($code)
    {
        $user = User::where("system_userid", $code)->first();
        return response([
            'status'    => true,
            'message'   => 'User datails fetched successfully.',
            'data'      =>  $user->load(["address", "kyc"])
        ], 200);
    }

    public function login(Request $request)
    {
        $isMobileUser = false;

        $rules = [
            'password' => 'required|string|min:8'
        ];

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

        $credentials = ["password" => $request->password];
        if ($isMobileUser) {
            $credentials['mobile'] = $request->email;
        } else {
            $credentials['email'] = $request->email;
        }

        $user = $isMobileUser ? User::where("mobile", $request->email)->first() : User::where("email", $request->email)->first();
        if ($user && $user->account_status === 'banned') {
            return response([
                'message' => "Some errors occured.",
                'error' => 'banned'
            ], 400);
        }

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

        $user = JWTAuth::user();
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
            'password'  => 'required|string|min:8'
        ];

        $errorMessages = [
            'required' => 'The :attribute field can not be blank.'
        ];

        if (is_numeric($request->email)) {
            $rules['email'] = 'required|digits:10';
            $errorMessages['email.digits'] = 'Mobile number is not valid (must be of 10 digits).';
            $isMobileUser = true;
        } else {
            $rules['email'] = 'required|email|unique:users';
        }

        $validator = Validator::make($request->all(), $rules, $errorMessages);

        if ($validator->fails()) {
            return response([
                'message' => 'Some errors occured',
                'error'   => $validator->errors()
            ], 400);
        }

        //check if already a user with mobile
        if ($isMobileUser && User::where('mobile', $request->email)->count()) {
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
        if ($isMobileUser) {
            $user->mobile = $request->email;
        } else {
            $user->email = $request->email;
        }
        $user->role = $request->role;
        $user->password = bcrypt($request->password);
        $user->referral_code = $request->referral_code;
        $user->system_ip = $request->ip();

        //generate new userid for user
        $cid = ["tenant" => "UID-0", "ibo" => "IID-0", "landlord" => "LID-0"];
        $user->system_userid = $cid[$request->role] . rand(11111, 99999);

        //save user to database
        if ($user->save()) {

            if ($request->referral_code) {
                $refuser = User::where("system_userid", $request->referral_code)->first();
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

            return response([
                'status' => true,
                'message' => 'User Registered successfully.',
                'user' => $user
            ], 200);
        } else {
            return response([
                'status' => false,
                'message' => 'Unable to register user.'
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
}
