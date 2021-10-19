<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\PropertyRatingAndReview;
use App\Models\User;
use App\Models\UserSavedProperty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserSavedPropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response([
            'status'    => false,
            'message'   => 'Action not allowed!'
        ], 401);
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
            'user_id'               => 'required',
            'property_id'           => 'required',
            'property_code'         => 'required',
            'type'                  => 'required|in:visited,saved,favorite',
            'property_name'         => 'required|string',
            'property_posted_by'    => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured!',
                'error'     => $validator->errors()
            ], 400);
        }

        $ratings = PropertyRatingAndReview::where("property_id", $request->property_id)->get();
        $total_rating = 0;
        foreach ($ratings as $r) {
            $total_rating += $r->rating;
        }

        $savedProperty = new UserSavedProperty;
        $savedProperty->user_id = $request->user_id;
        $savedProperty->property_id = $request->property_id;
        $savedProperty->property_code = $request->property_code;
        $savedProperty->type = $request->type;
        $savedProperty->rating = $total_rating / count($ratings);
        $savedProperty->property_name = $request->property_name;
        $savedProperty->property_short_description = isset($request->property_short_description) ? $request->property_short_description : '';
        $savedProperty->front_image = isset($request->front_image) ? $request->front_image : '';
        $savedProperty->property_posted_by = $request->property_posted_by;

        if (UserSavedProperty::where("property_id", $request->property_id)->where("type", $request->type)->where("user_id", $request->user_id)->count() > 0) {
            return response([
                'status'    => true,
                'message'   => 'Saved already.',
            ], 200);
        }

        if ($savedProperty->save()) {
            return response([
                'status'    => true,
                'message'   => 'Property saved successfully.',
                'data'      => $savedProperty
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!',
        ], 500);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    /**search saved using custom params */
    public function search(Request $request)
    {
        if ($request->has('property_id')) {
            $property = UserSavedProperty::where("property_id", $request->property_id);
            if ($request->has('type')) {
                $property->where("type", $request->type);
            }
            if ($request->has('user_id')) {
                $property->where("user_id", $request->user_id);
            }
            return response([
                'status'    => true,
                'message'   => 'Fetched successfully.',
                'data'      => $property->get()
            ], 200);
        }

        if ($request->has('user_id')) {
            $property = UserSavedProperty::where("user_id", $request->user_id);
            if ($request->has('type')) {
                $property->where("type", $request->type);
            }
            if ($request->has('user_id')) {
                $property->where("user_id", $request->user_id);
            }
            return response([
                'status'    => true,
                'message'   => 'Fetched successfully.',
                'data'      => $property->get()
            ], 200);
        }

        if ($request->has('property_code')) {
            $property = UserSavedProperty::where("property_code", $request->property_code);
            if ($request->has('type')) {
                $property->where("type", $request->type);
            }
            if ($request->has('user_id')) {
                $property->where("user_id", $request->user_id);
            }
            return response([
                'status'    => true,
                'message'   => 'Fetched successfully.',
                'data'      => $property->get()
            ], 200);
        }
    }

    public function show($id)
    {
        $user = User::find($id);
        $alldata = UserSavedProperty::where("user_id", $user->id)->get()->map(function ($d) {
            $ratings = PropertyRatingAndReview::where("property_id", $d->property_id)->get();
            $total_rating = 0;
            foreach ($ratings as $r) {
                $total_rating += $r->rating;
            }
            $d->rating = $total_rating / count($ratings);
            if (is_numeric($d->property_posted_by)) {
                $d->property_posted_by = User::find((int)$d->property_posted_by)->first;
                return $d;
            } else {
                return $d;
            }
        });

        if ($user) {
            return response([
                'status'    => true,
                'message'   => 'Saved properties of user fetched successfully.',
                'data'      => $alldata
            ], 200);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $savedp = UserSavedProperty::find($id);
        if ($savedp) {
            $savedp->delete();
            return response([
                'status'    => true,
                'message'   => 'Deleted successfully.',
                'data'      => $savedp
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!',
        ], 500);
    }
}
