<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\PropertyRatingAndReview;
use Illuminate\Http\Request;
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

        $review = PropertyRatingAndReview::where("user_id", JWTAuth::user() ? JWTAuth::user()->id : 0)
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
