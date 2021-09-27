<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Tymon\JWTAuth\Claims\JwtId;
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
        $this->middleware('jwt.verify', ['except' => ['login', 'signup']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function login(Request $request)
    {
        $isMobileUser = false;

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8'
        ]);

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

        if (!$token = JWTAuth::attempt($credentials)) {
            return response([
                'message' => $isMobileUser ? "Mobile or Password is wrong!" : "Email or Password is wrong!"
            ], 401);
        }
        $user = JWTAuth::user();
        $user->is_logged_in = 1;
        $user->save();
        $info = [
            'id'       => $user->id,
            'first'    => $user->first,
            'last'    => $user->last,
            'fullname' => $user->first . ' ' . $user->last,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'role' => $user->role,
            'profile_pic' => $user->profile_pic,
            'permissions' => []
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
        $user->system_ip = $request->ip();

        //save user to database
        if ($user->save()) {
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
            $user = JWTAuth::user()->load('address');
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
