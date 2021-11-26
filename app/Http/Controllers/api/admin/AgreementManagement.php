<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;

class AgreementManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $agreements = Agreement::all()->map(function ($a) {
            if ($a) {
                $a->property_data = Property::find($a->property_id) ? Property::find($a->property_id)->only(['front_image', 'name', 'property_code', 'bedrooms', 'bathrooms', 'floors', 'monthly_rent', 'maintenence_charge', 'country_name', 'state_name', 'city_name']) : '';
                $a->landlord = User::find($a->landlord_id)->only(['first', 'last', 'profile_pic', 'email', 'mobile']);
                $a->ibo = User::find($a->ibo_id)->only(['first', 'last', 'profile_pic', 'email', 'mobile']);
                $a->tenant = User::find($a->tenant_id)->only(['first', 'last', 'profile_pic', 'email', 'mobile']);
            }
            return $a;
        });
        if ($agreements) {
            return response([
                'status'    => true,
                'message'   => 'Agreements fetched successfully.',
                'data'      => $agreements
            ], 200);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        return response([
            'status'    =>  false,
            'message'   => 'Action is not active!'
        ], 403);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $agreement = Agreement::find($id);
        if ($agreement) {
            return response([
                'status'    => true,
                'message'   => 'Agreement fetched successfully.',
                'data'      => $agreement
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Agreement not found!'
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
        return response([
            'status'    =>  false,
            'message'   => 'Action is not active!'
        ], 403);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        return response([
            'status'    =>  false,
            'message'   => 'Action is not active!'
        ], 403);
    }
}
