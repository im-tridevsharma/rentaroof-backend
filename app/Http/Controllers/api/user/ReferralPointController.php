<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class ReferralPointController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.verify');
    }

    //get referral of user
    public function getReferrals(Request $request)
    {
        $user = JWTAuth::user();
        if ($user) {
            $history = DB::table('user_referral_points')->where("user_id", $user->id)->orderBy("id", "desc");

            if ($request->has('for')) {
                $history->where("for", $request->for);
            }

            if ($request->has('type')) {
                $history->where("type", $request->type);
            }

            $history = $history->get();

            return response([
                'status'    => true,
                'message'   => 'Points history fetched successfully.',
                'data'      => $history
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found!'
        ], 404);
    }
}
