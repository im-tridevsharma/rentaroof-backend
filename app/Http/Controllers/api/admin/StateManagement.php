<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Country;
use App\Models\State;

class StateManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $states = State::all();
        return response([
            'status'  => true,
            'message' => 'States fetched successfully.',
            'data'    => $states->load('country')
        ], 200);
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
            'name' => 'required|string|max:50',
            'country_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response([
                'status'  => false,
                'message' => 'Some errors occured.',
                'error'   => $validator->errors()
            ], 400);
        }

        //check if country exists with provided id
        if (!Country::find($request->country_id)) {
            return response([
                'status'  => false,
                'message' => 'Failed to find country you selected.'
            ], 404);
        }

        //save state
        $state = State::create($validator->Validated());
        if ($state) {
            return response([
                'status'  => true,
                'message' => 'New state created successfully.',
                'data'    => $state
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'Failed to add new state.'
        ], 404);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $state = State::find($id);
        if ($state) {
            return response([
                'status'  => true,
                'message' => 'State fetched successfully.',
                'data'    => $state
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'State not found.'
        ], 404);
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
            'name' => 'required|string|max:50',
            'country_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response([
                'status'  => false,
                'message' => 'Some errors occured.',
                'error'   => $validator->errors()
            ], 400);
        }

        //check if country exists with provided id
        if (!Country::find($request->country_id)) {
            return response([
                'status'  => false,
                'message' => 'Failed to find country you selected.'
            ], 404);
        }

        $state = State::find($id);
        if ($state) {
            $state->name = $request->name;
            $state->country_id = $request->country_id;
            $state->save();

            return response([
                'status'  => true,
                'message' => 'State updated successfully.',
                'data'    => $state
            ]);
        }

        return response([
            'status'  => false,
            'message' => 'State not found.'
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
        $state = State::find($id);
        if ($state) {
            $state->delete();

            return response([
                'status'  => true,
                'message' => 'State deleted successfully.',
                'data'    => $state
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'State not found.'
        ], 404);
    }
}
