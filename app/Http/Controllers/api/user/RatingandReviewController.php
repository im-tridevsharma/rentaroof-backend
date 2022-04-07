<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyRatingAndReview;
use App\Models\Wallet;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class RatingandReviewController extends Controller
{
    public function index()
    {
        return response([
            'status'    => false,
            'message'   => "action not allowed"
        ]);
    }
    //save rating and review
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'review'    => 'required|string|max:250',
            'rating'    => 'required',
            'property_id'   => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $user = JWTAuth::user();

        $review = new PropertyRatingAndReview;
        $review->title = $request->has('title') ? $request->title : '';
        $review->property_id = $request->has('property_id') ? $request->property_id : '';
        $review->description = $request->has('review') ? $request->review : '';
        $review->user_id = $user ? $user->id : NULL;
        $review->full_name = $user ? $user->first . ' ' . $user->last : 'Guest';
        $review->email = $user ? (!empty($user->email) ? $user->email : '') : '';
        $review->mobile = $user ? (!empty($user->mobile) ? $user->mobile : '') : '';
        $review->rating = $request->has('rating') ? $request->rating : 0;

        $is = PropertyRatingAndReview::where("property_id", $request->property_id)
            ->where("user_id", $user ? $user->id : NULL)->first();

        if ($is) {
            return $this->update($request, $is->id);
        } else {
            if ($review->save()) {

                if ($user) {
                    //get settings for points
                    $point_value  = DB::table('settings')->where("setting_key", "point_value")->first()->setting_value;
                    $review_point = DB::table('settings')->where("setting_key", "review_point")->first()->setting_value;

                    $points = floatval($point_value) * floatval($review_point);
                    $property = Property::find($request->property_id);

                    //point data
                    $pdata = [
                        "user_id"   => $user->id,
                        "role"      => $user->role,
                        "title"     => "You earned " . $review_point . " points for review property-" . $property->property_code,
                        "point_value"   => $point_value,
                        "points"    => $review_point,
                        "amount_earned" => $points,
                        "type"  => "credit",
                        "for"  => "review",
                        "created_at"    => date("Y-m-d H:i:s"),
                        "updated_at"    => date("Y-m-d H:i:s"),
                    ];

                    DB::table('user_referral_points')->insert($pdata);

                    try {
                        //add amount to wallet
                        $wallet = Wallet::where("user_id", $user->id)->first();
                        $wallet->amount += floatval($points);
                        $wallet->credit += floatval($points);
                        $wallet->last_credit_transaction = date('Y-m-d H:i:s');
                        $wallet->last_transaction_type = 'credit';
                        $wallet->save();
                    } catch (Exception $e) {
                        //
                    }
                }

                return response([
                    'status'    => true,
                    'message'   => 'Review saved successfully.',
                    'data'      => $review
                ], 200);
            }
            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }
    }

    //show review
    public function show($id)
    {
        $review = PropertyRatingAndReview::find($id);
        if ($review) {
            return response([
                'status'    => true,
                'message'   => 'Review fetched successfully.',
                'data'      => $review
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Review not found!'
        ], 404);
    }

    //get all reviews of a property
    public function all($id)
    {
        $reviews = PropertyRatingAndReview::where("property_id", $id)->get();
        if ($reviews) {
            return response([
                'status'    => true,
                'message'   => 'Reviews fetched successfully.',
                'data'      => $reviews
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Reviews not found!'
        ], 404);
    }

    //update property
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'review'    => 'required|string|max:250',
            'rating'    => 'required',
            'property_id'   => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $user = JWTAuth::user();

        $review = PropertyRatingAndReview::where("property_id", $request->property_id)->where("user_id", JWTAuth::user() ? JWTAuth::user()->id : 0)
            ->where("id", $id)->first();
        if ($review) {
            $review->title = $request->has('title') ? $request->title : '';
            $review->property_id = $request->has('property_id') ? $request->property_id : '';
            $review->description = $request->has('review') ? $request->review : '';
            $review->user_id = $user ? $user->id : NULL;
            $review->full_name = $user ? $user->first . ' ' . $user->last : 'Guest';
            $review->email = $user ? $user->email : '';
            $review->mobile = $user ? $user->mobile : '';
            $review->rating = $request->has('rating') ? $request->rating : 0;

            if ($review->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Review saved successfully.',
                    'data'      => $review
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }
        return response([
            'status'    => false,
            'message'   => 'Review not found!'
        ], 404);
    }

    //show review
    public function destroy($id)
    {
        $review = PropertyRatingAndReview::where("user_id", JWTAuth::user()->id)->first();
        if ($review) {
            $review->delete();
            return response([
                'status'    => true,
                'message'   => 'Review deleted successfully.',
                'data'      => $review
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Review not found!'
        ], 404);
    }
}
