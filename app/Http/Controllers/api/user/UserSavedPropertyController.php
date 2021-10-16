<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
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
            'property_posted_by'    => 'required|string'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured!',
                'error'     => $validator->errors()
            ], 400);
        }

        $savedProperty = new UserSavedProperty;
        $savedProperty->user_id = $request->user_id;
        $savedProperty->property_id = $request->property_id;
        $savedProperty->property_code = $request->property_code;
        $savedProperty->type = $request->type;
        $savedProperty->property_name = $request->property_name;
        $savedProperty->property_short_description = isset($request->property_short_description) ? $request->property_short_description : '';
        $savedProperty->front_image = isset($request->front_image) ? $request->front_image : '';
        $savedProperty->rating = isset($request->rating) ? $request->rating : '';
        $savedProperty->property_posted_by = $request->property_posted_by;

        if (UserSavedProperty::where("property_id", $request->property_id)->where("type", $request->type)->count() > 0) {
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
    public function show($id)
    {
        $user = User::find($id);
        if ($user) {
            return response([
                'status'    => true,
                'message'   => 'Saved properties of user fetched successfully.',
                'data'      => UserSavedProperty::where("user_id", $user->id)->get()
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
