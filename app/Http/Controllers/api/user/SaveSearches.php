<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\UserSavedSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SaveSearches extends Controller
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
            'message' => 'Action not allowed!'
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
            'user_id'   => 'required',
            'search'    => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $search = new UserSavedSearch;
        $search->user_id = $request->user_id;
        $search->search  = $request->search;

        $is = UserSavedSearch::where("search", $request->search)->first();
        if (!$is) {
            $search->save();
            return response([
                'status'    => true,
                'message'   => 'Search saved successfully.'
            ], 200);
        }

        return response([
            'status'    => true,
            'message'   => 'Search exists already.'
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $search = UserSavedSearch::find($id);
        if ($search) {
            return response([
                'status'    => true,
                'message'   => 'User property searches fetched successfully.',
                'data'      => $search
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!'
        ], 500);
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
        return response([
            'status'    => false,
            'message'   => 'Method not allowed.'
        ], 401);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $search = UserSavedSearch::find($id);
        if ($search) {
            $search->delete();
            return response([
                'status'    => true,
                'message'   => 'Search deleted successfully.',
                'data'      => $search
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Search not found!'
        ], 404);
    }
}
