<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\Preference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PreferenceManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $preferences = Preference::all();
        return response([
            'status'  => true,
            'message' => 'Preferences fetched successfully.',
            'data'    => $preferences
        ]);
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
            'title'          => 'required|string|between:5,50',
            'description'    => 'max:200',
        ]);

        if ($validator->fails()) {
            return response([
                'status'    =>  false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $preference = new Preference;
        $preference->title = $request->title;
        $preference->description = isset($request->description) ? $request->description : '';

        if ($preference->save()) {
            return response([
                'status'    => true,
                'message'   => 'New Preference added successfully.',
                'data'      => $preference
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Unable to save preference data.'
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
        $preference = Preference::find($id);

        if ($preference) {
            return response([
                'status'    => true,
                'message'   => 'Preference fetched successfully.',
                'data'      => $preference
            ]);
        }

        return response([
            'status'    => false,
            'message'   => 'Preference not found.'
        ], 400);
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
            'title'          => 'required|string|between:5,50',
            'description'    => 'max:200',
        ]);

        if ($validator->fails()) {
            return response([
                'status'    =>  false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $preference = Preference::find($id);
        if ($preference) {
            $preference->title = $request->title;
            $preference->description = isset($request->description) ? $request->description : '';

            if ($preference->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Preference update successfully.',
                    'data'      => $preference
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Unable to update preference data.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Unable to find preference.'
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
        $preference = Preference::find($id);

        if ($preference) {
            $preference->delete();
            return response([
                'status'    => true,
                'message'   => 'Preference deleted successfully.',
                'data'      => $preference
            ]);
        }

        return response([
            'status'    => false,
            'message'   => 'Preference not found.'
        ], 400);
    }
}
